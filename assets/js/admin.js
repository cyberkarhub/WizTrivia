jQuery(document).ready(function($) {
    let triviaData = triviaAdminSettings.initialData || {}; // Load initial data passed from PHP
    const saveAllDataToServerBtn = $('#saveAllDataToServerBtn');
    const questionForm = $('#questionForm');
    const questionsTableBody = $('#questionsTable tbody');
    const topicInput = $('#topic');
    const levelInput = $('#level');
    const questionTextInput = $('#questionText');
    const optionsInput = $('#options');
    const correctAnswerInput = $('#correctAnswer');
    const sourceUrlInput = $('#sourceUrl');
    const sourceTitleInput = $('#sourceTitle');
    const levelArticleUrlInput = $('#levelArticleUrl');
    const levelArticleTitleInput = $('#levelArticleTitle');

    const editModeInput = $('#editMode');
    const editTopicNameInput = $('#editTopicName');
    const editLevelIndexInput = $('#editLevelIndex');
    const editQuestionIndexInput = $('#editQuestionIndex');
    const clearFormBtn = $('#clearFormBtn');
    const mainFormAreaTitle = $('#mainFormAreaTitle');
    const currentActionTitleContainer = $('#currentActionTitleContainer');

    const deleteConfirmModal = $('#deleteConfirmModal');
    const confirmDeleteBtn = $('#confirmDeleteBtn');
    const deleteModalText = $('#deleteModalText');
    let itemToDelete = null;

    const selectArticleTopic = $('#selectArticleTopic');
    const selectArticleLevel = $('#selectArticleLevel');
    const loadArticleForSelectedLevelBtn = $('#loadArticleForSelectedLevelBtn');
    const saveLevelArticleBtn = $('#saveLevelArticleBtn');

    const aiGenerationForm = $('#aiGenerationForm');
    const saveAISettingsBtn = $('#saveAISettingsBtn');
    const generateAIBtn = $('#generateAIBtn');
    const aiGenerationStatusDiv = $('#aiGenerationStatus');
    let jobStatusInterval = null;


    // --- Utility Functions ---
    function sanitizeInput(str, isUrl = false) {
        if (typeof str !== 'string') return '';
        let val = str.trim();
        if (!isUrl) {
            // Basic sanitization: remove common HTML tags and trim.
            // For more robust sanitization, consider a library or more specific regex.
            // This is primarily for display consistency, server-side sanitization is key for security.
            val = val.replace(/<\/?[^>]+(>|$)/g, ""); // Strip tags
        }
        // Further sanitization to prevent XSS if this data is ever directly output without WP escaping
        // For display in input fields, quotes might be okay if attributes are properly quoted.
        // However, for general safety:
        if (!isUrl) {
             val = val.replace(/[<>"']/g, function(match) { // Simplified: remove problematic chars
                return '';
            });
        }
        return val;
    }

    function showMessage(message, isSuccess, $elementToScrollTo = null, autoClear = true) {
        const $statusDiv = $('#formStatusMessage'); // Assuming you have a div for this
        if (!$statusDiv.length) {
            // Create it if it doesn't exist, or use aiGenerationStatusDiv as a fallback
            // For now, let's use aiGenerationStatusDiv if #formStatusMessage is not present
            const $fallbackStatusDiv = aiGenerationStatusDiv;
            $fallbackStatusDiv.text(message).removeClass('hidden').show();
            if (isSuccess) {
                $fallbackStatusDiv.css({'background-color': '#28a745', 'color': 'white', 'border': '1px solid #1e7e34'});
            } else {
                $fallbackStatusDiv.css({'background-color': '#dc3545', 'color': 'white', 'border': '1px solid #c82333'});
            }
            if ($elementToScrollTo && $elementToScrollTo.length) {
                $('html, body').animate({ scrollTop: $elementToScrollTo.offset().top - 50 }, 500);
            }
            if (autoClear) {
                setTimeout(() => $fallbackStatusDiv.fadeOut(500, () => $fallbackStatusDiv.addClass('hidden')), 5000);
            }
            return;
        }

        // Original logic if #formStatusMessage exists
        $statusDiv.text(message).removeClass('hidden alert-success alert-danger').addClass(isSuccess ? 'alert-success' : 'alert-danger').fadeIn();
        if ($elementToScrollTo && $elementToScrollTo.length) {
            $('html, body').animate({ scrollTop: $elementToScrollTo.offset().top - 50 }, 500);
        }
        if (autoClear) {
            setTimeout(() => $statusDiv.fadeOut(500, () => $statusDiv.addClass('hidden')), 5000);
        }
    }

    function updateCurrentActionTitle(title) {
        if (title) {
            currentActionTitleContainer.text(title).removeClass('hidden');
        } else {
            currentActionTitleContainer.addClass('hidden').text('');
        }
    }

    // --- Data Handling and Rendering ---
    function renderQuestionsTable() {
        questionsTableBody.empty();
        if ($.isEmptyObject(triviaData)) {
            questionsTableBody.append('<tr><td colspan="6" class="text-center">No trivia data loaded or available. Add questions manually or use AI generation.</td></tr>');
            return;
        }
        let questionCount = 0;
        for (const topicName in triviaData) {
            if (triviaData.hasOwnProperty(topicName) && Array.isArray(triviaData[topicName])) {
                triviaData[topicName].forEach((levelData, levelIndex) => {
                    if (levelData.questions && Array.isArray(levelData.questions)) {
                        levelData.questions.forEach((question, questionIndex) => {
                            questionCount++;
                            const sourceDisplay = question.sourceUrl ? `<a href="${question.sourceUrl}" target="_blank" title="${question.sourceTitle || ''}">${question.sourceTitle || question.sourceUrl}</a>` : 'N/A';
                            const row = `
                                <tr data-topic="${topicName}" data-level="${levelIndex}" data-question="${questionIndex}">
                                    <td>${sanitizeInput(topicName)}</td>
                                    <td>${levelIndex + 1}</td>
                                    <td>${sanitizeInput(question.question)}</td>
                                    <td>${sanitizeInput(question.answer)}</td>
                                    <td>${sourceDisplay}</td>
                                    <td class="actions-cell">
                                        <button class="btn edit-btn"><i class="fas fa-edit"></i>Edit</button>
                                        <button class="btn delete-btn"><i class="fas fa-trash-alt"></i>Delete</button>
                                    </td>
                                </tr>`;
                            questionsTableBody.append(row);
                        });
                    }
                });
            }
        }
        if (questionCount === 0) {
             questionsTableBody.append('<tr><td colspan="6" class="text-center">No questions found in the current data.</td></tr>');
        }
    }

    function populateTopicAndLevelDropdowns() {
        selectArticleTopic.empty().append('<option value="">-- Select Topic --</option>');
        selectArticleLevel.empty().append('<option value="">-- Select Level --</option>').prop('disabled', true);

        const topics = Object.keys(triviaData);
        if (topics.length === 0) {
            selectArticleTopic.append('<option value="" disabled>No topics available</option>');
            return;
        }
        topics.forEach(topic => {
            selectArticleTopic.append(`<option value="${sanitizeInput(topic)}">${sanitizeInput(topic)}</option>`);
        });
    }

    selectArticleTopic.on('change', function() {
        const selectedTopic = $(this).val();
        selectArticleLevel.empty().append('<option value="">-- Select Level --</option>');
        if (selectedTopic && triviaData[selectedTopic] && Array.isArray(triviaData[selectedTopic])) {
            triviaData[selectedTopic].forEach((level, index) => {
                selectArticleLevel.append(`<option value="${index}">Level ${index + 1}</option>`);
            });
            selectArticleLevel.prop('disabled', false);
        } else {
            selectArticleLevel.prop('disabled', true);
        }
        // Clear article form fields when topic changes
        levelArticleUrlInput.val('');
        levelArticleTitleInput.val('');
    });


    // --- Form Operations ---
    function clearForm() {
        questionForm[0].reset();
        editModeInput.val('false');
        editTopicNameInput.val('');
        editLevelIndexInput.val('');
        editQuestionIndexInput.val('');
        mainFormAreaTitle.text('Manage Trivia Data (Questions & Levels)');
        updateCurrentActionTitle(''); // Clear action title
        topicInput.prop('readonly', false); // Make topic editable for new entries
        levelInput.prop('readonly', false); // Make level editable for new entries
        questionTextInput.focus();
    }

    function loadQuestionIntoForm(topicName, levelIndex, questionIndex) {
        const questionData = triviaData[topicName][levelIndex].questions[questionIndex];
        if (!questionData) {
            showMessage('Error: Question data not found for editing.', false, mainFormAreaTitle);
            return;
        }

        topicInput.val(topicName).prop('readonly', true); // Keep topic read-only during edit
        levelInput.val(parseInt(levelIndex, 10) + 1).prop('readonly', true); // Keep level read-only during edit

        questionTextInput.val(questionData.question);
        optionsInput.val(questionData.options.join(','));
        correctAnswerInput.val(questionData.answer);
        sourceUrlInput.val(questionData.sourceUrl || '');
        sourceTitleInput.val(questionData.sourceTitle || '');

        // Load level article info if it exists for this level
        const levelData = triviaData[topicName][levelIndex];
        levelArticleUrlInput.val(levelData.levelArticleUrl || '');
        levelArticleTitleInput.val(levelData.levelArticleTitle || '');


        editModeInput.val('true');
        editTopicNameInput.val(topicName);
        editLevelIndexInput.val(levelIndex);
        editQuestionIndexInput.val(questionIndex);

        mainFormAreaTitle.text(`Editing Question (Topic: ${sanitizeInput(topicName)}, Level: ${parseInt(levelIndex, 10) + 1})`);
        updateCurrentActionTitle(`Editing Question: "${sanitizeInput(questionData.question.substring(0,30))}..."`);
        questionTextInput.focus();
        $('html, body').animate({ scrollTop: questionForm.offset().top - 50 }, 500);
    }

    questionForm.on('submit', function(e) {
        e.preventDefault();
        const topic = sanitizeInput(topicInput.val());
        const level = parseInt(levelInput.val(), 10) - 1; // 0-indexed
        const questionText = sanitizeInput(questionTextInput.val());
        const optionsArray = optionsInput.val().split(',').map(opt => sanitizeInput(opt.trim())).filter(opt => opt !== '');
        const correctAnswer = sanitizeInput(correctAnswerInput.val());
        const sourceUrl = sanitizeInput(sourceUrlInput.val(), true);
        const sourceTitle = sanitizeInput(sourceTitleInput.val());

        if (!topic || level < 0 || !questionText || optionsArray.length < 2 || !correctAnswer) {
            showMessage('Error: Please fill all required fields (Topic, Level, Question, Options, Correct Answer). Options must be at least 2.', false, mainFormAreaTitle);
            return;
        }
        if (!optionsArray.includes(correctAnswer)) {
            showMessage('Error: Correct answer must be one of the provided options.', false, mainFormAreaTitle);
            return;
        }

        const questionData = {
            question: questionText,
            options: optionsArray,
            answer: correctAnswer,
            sourceUrl: sourceUrl,
            sourceTitle: sourceTitle
        };

        const isEditMode = editModeInput.val() === 'true';
        const editTopic = sanitizeInput(editTopicNameInput.val());
        const editLevel = parseInt(editLevelIndexInput.val(), 10);
        const editQuestion = parseInt(editQuestionIndexInput.val(), 10);

        if (!triviaData[topic]) {
            triviaData[topic] = [];
        }
        if (!triviaData[topic][level]) {
            // Initialize level with default article info if creating new level
            triviaData[topic][level] = {
                levelArticleUrl: sanitizeInput(levelArticleUrlInput.val(), true),
                levelArticleTitle: sanitizeInput(levelArticleTitleInput.val()),
                questions: []
            };
        } else {
            // If level exists, update its article info from the form (might be redundant if saveLevelArticleBtn is used, but good for consistency)
             triviaData[topic][level].levelArticleUrl = sanitizeInput(levelArticleUrlInput.val(), true);
             triviaData[topic][level].levelArticleTitle = sanitizeInput(levelArticleTitleInput.val());
        }


        if (isEditMode && topic === editTopic && level === editLevel) {
            triviaData[editTopic][editLevel].questions[editQuestion] = questionData;
            showMessage('Question updated successfully in memory.', true, mainFormAreaTitle);
        } else {
            // This handles adding a new question or if topic/level changed during an "edit" (effectively a new question)
            if (!triviaData[topic][level].questions) {
                triviaData[topic][level].questions = [];
            }
            triviaData[topic][level].questions.push(questionData);
            showMessage('Question added successfully in memory.', true, mainFormAreaTitle);
        }

        renderQuestionsTable();
        populateTopicAndLevelDropdowns(); // Keep dropdowns updated
        clearForm();
    });


    // --- Button Event Handlers ---
    clearFormBtn.on('click', clearForm);

    saveAllDataToServerBtn.on('click', function() {
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
        $.ajax({
            url: triviaAdminSettings.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wiz_save_trivia_data',
                nonce: triviaAdminSettings.nonce,
                trivia_data: JSON.stringify(triviaData)
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message || 'Data saved successfully!', true, saveAllDataToServerBtn);
                } else {
                    showMessage(response.data.message || 'Failed to save data.', false, saveAllDataToServerBtn);
                }
            },
            error: function(xhr, txt, err) {
                let msg = 'AJAX Error: ' + txt;
                try {
                    if (xhr.responseText) {
                        const json = JSON.parse(xhr.responseText);
                        if (json.data && json.data.message) msg = json.data.message;
                        else if (json.message) msg = json.message; // For other error structures
                    }
                } catch (e) { console.error("Error parsing AJAX error response:", e); }
                showMessage(msg, false, saveAllDataToServerBtn, false); // Don't autoclear error
                console.error("AJAX save error:", err, xhr.responseText);
            },
            complete: function() {
                saveAllDataToServerBtn.prop('disabled', false).html('<i class="fas fa-server"></i> Save All Trivia Data to Server');
            }
        });
    });

    questionsTableBody.on('click', '.edit-btn', function() {
        const row = $(this).closest('tr');
        const topicName = row.data('topic');
        const levelIndex = row.data('level');
        const questionIndex = row.data('question');
        loadQuestionIntoForm(topicName, levelIndex, questionIndex);
    });

    questionsTableBody.on('click', '.delete-btn', function() {
        const row = $(this).closest('tr');
        itemToDelete = {
            topicName: row.data('topic'),
            levelIndex: parseInt(row.data('level'), 10),
            questionIndex: parseInt(row.data('question'), 10)
        };
        const questionTextPreview = triviaData[itemToDelete.topicName][itemToDelete.levelIndex].questions[itemToDelete.questionIndex].question.substring(0, 100) + "...";
        deleteModalText.html(`Topic: <strong>${sanitizeInput(itemToDelete.topicName)}</strong>, Level: <strong>${itemToDelete.levelIndex + 1}</strong><br>Question: <em>${sanitizeInput(questionTextPreview)}</em>`);
        deleteConfirmModal.removeClass('hidden');
    });

    confirmDeleteBtn.on('click', function() {
        if (itemToDelete) {
            const { topicName, levelIndex, questionIndex } = itemToDelete;
            if (triviaData[topicName] && triviaData[topicName][levelIndex] && triviaData[topicName][levelIndex].questions) {
                triviaData[topicName][levelIndex].questions.splice(questionIndex, 1);

                // Clean up empty levels or topics
                if (triviaData[topicName][levelIndex].questions.length === 0 &&
                    (!triviaData[topicName][levelIndex].levelArticleUrl && !triviaData[topicName][levelIndex].levelArticleTitle)) { // Only remove if no article info either
                    triviaData[topicName].splice(levelIndex, 1);
                }
                if (triviaData[topicName].length === 0) {
                    delete triviaData[topicName];
                }
                renderQuestionsTable();
                populateTopicAndLevelDropdowns();
                showMessage('Question deleted from memory.', true, mainFormAreaTitle);
            }
        }
        closeModal('deleteConfirmModal');
        itemToDelete = null;
    });

    // Modal close functionality
    window.closeModal = function(modalId) { // Make it global for inline onclick
        $('#' + modalId).addClass('hidden');
    };
    $('.modal .close-button').on('click', function() {
        $(this).closest('.modal').addClass('hidden');
    });
    // Close modal on ESC key
    $(document).on('keydown', function(event) {
        if (event.key === "Escape") {
            $('.modal').addClass('hidden');
        }
    });


    // === Manage Level Article Info ===
    loadArticleForSelectedLevelBtn.on('click', function() {
        const topic = selectArticleTopic.val();
        const levelIndex = parseInt(selectArticleLevel.val(), 10);

        if (topic && levelIndex >= 0 && triviaData[topic] && triviaData[topic][levelIndex]) {
            const levelData = triviaData[topic][levelIndex];
            levelArticleUrlInput.val(levelData.levelArticleUrl || '');
            levelArticleTitleInput.val(levelData.levelArticleTitle || '');
            topicInput.val(topic); // Populate main form topic/level for context
            levelInput.val(levelIndex + 1);
            showMessage(`Loaded article info for ${sanitizeInput(topic)} - Level ${levelIndex + 1}. You can now edit it in the form below.`, true, mainFormAreaTitle);
             updateCurrentActionTitle(`Managing Article for Topic: ${sanitizeInput(topic)}, Level: ${levelIndex + 1}`);
            $('html, body').animate({ scrollTop: questionForm.offset().top - 50 }, 500); // Scroll to form
        } else {
            levelArticleUrlInput.val('');
            levelArticleTitleInput.val('');
            topicInput.val(''); // Clear if no valid selection
            levelInput.val('');
            showMessage('Please select a valid topic and level to load article info.', false, mainFormAreaTitle);
             updateCurrentActionTitle('');
        }
    });

    saveLevelArticleBtn.on('click', function() {
        const topic = sanitizeInput(topicInput.val()); // Get topic from the main form
        const level = parseInt(levelInput.val(), 10) - 1; // 0-indexed from main form
        const articleUrl = sanitizeInput(levelArticleUrlInput.val(), true);
        const articleTitle = sanitizeInput(levelArticleTitleInput.val());

        if (!topic || level < 0) {
            showMessage('Error: Please specify Topic and Level in the form above before saving article info.', false, mainFormAreaTitle);
            return;
        }

        if (!triviaData[topic]) {
            triviaData[topic] = [];
        }
        if (!triviaData[topic][level]) {
            triviaData[topic][level] = { questions: [] }; // Create level if it doesn't exist
        }

        triviaData[topic][level].levelArticleUrl = articleUrl;
        triviaData[topic][level].levelArticleTitle = articleTitle;

        showMessage(`Article info for ${sanitizeInput(topic)} - Level ${level + 1} saved in memory.`, true, mainFormAreaTitle);
        // No need to re-render table or dropdowns unless structure changes.
    });

    // Logo Uploader
    let mediaUploader;
    $('.wiz-trivia-upload-logo-button').on('click', function(e) {
        e.preventDefault();
        const $thisButton = $(this);
        const $urlField = $thisButton.siblings('.wiz-trivia-logo-url-field');
        const $previewDiv = $thisButton.siblings('.wiz-trivia-logo-preview');
        const $removeButton = $thisButton.siblings('.wiz-trivia-remove-logo-button');

        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: 'Choose Logo',
            button: { text: 'Choose Logo' },
            multiple: false
        });
        mediaUploader.on('select', function() {
            const attachment = mediaUploader.state().get('selection').first().toJSON();
            $urlField.val(attachment.url);
            $previewDiv.html(`<img src="${attachment.url}" alt="Logo Preview" style="max-width:250px; max-height:100px;">`);
            $removeButton.show();
        });
        mediaUploader.open();
    });

    $('.wiz-trivia-remove-logo-button').on('click', function(e) {
        e.preventDefault();
        const $thisButton = $(this);
        const $urlField = $thisButton.siblings('.wiz-trivia-logo-url-field');
        const $previewDiv = $thisButton.siblings('.wiz-trivia-logo-preview');
        const fallbackLogo = 'https://digitrendz.blog/wp-content/uploads/2025/05/digitrendz-New-Logo-4a0538.svg'; // Ensure this is defined or passed correctly

        $urlField.val(fallbackLogo); // Set to fallback or empty if preferred
        $previewDiv.html(`<img src="${fallbackLogo}" alt="Default Logo" style="max-width:250px; max-height:100px;">`);
        // $previewDiv.html(`<span class="no-logo-text" style="color:#ccc;">No custom logo selected.</span>`);
        $thisButton.hide();
    });


    // --- AI Generation Section ---
    saveAISettingsBtn.on('click', function() {
        const $button = $(this);
        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving AI Settings...');
        aiGenerationStatusDiv.addClass('hidden').text('');

        const apiKey = $('#wiz_trivia_ai_api_key').val();
        const aiProvider = $('#wiz_trivia_ai_provider').val();
        const primaryBlogDomain = $('#wiz_trivia_primary_blog_domain').val();
        const allowExternalSources = $('#wiz_trivia_allow_external_sources').is(':checked');


        $.ajax({
            url: triviaAdminSettings.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wiz_save_ai_settings',
                nonce: triviaAdminSettings.nonce, // General admin nonce
                api_key: apiKey,
                ai_provider: aiProvider,
                primary_blog_domain: primaryBlogDomain,
                allow_external_sources: allowExternalSources ? '1' : '0'
            },
            success: function(response) {
                if (response.success) {
                    aiGenerationStatusDiv.text(response.data.message || 'AI settings saved!').removeClass('hidden').css('background-color', '#2dce89').css('color', 'white');
                } else {
                    aiGenerationStatusDiv.text(response.data.message || 'Failed to save AI settings.').removeClass('hidden').css('background-color', '#f5365c').css('color', 'white');
                }
            },
            error: function(xhr, txt, err) {
                let msg = 'AJAX Error: ' + txt;
                try {
                    if (xhr.responseText) {
                        const json = JSON.parse(xhr.responseText);
                        if (json.data && json.data.message) msg = json.data.message;
                         else if (json.message) msg = json.message;
                    }
                } catch (e) {}
                aiGenerationStatusDiv.text(msg).removeClass('hidden').css('background-color', '#f5365c').css('color', 'white');
                console.error("AJAX save AI settings error:", err, xhr.responseText);
            },
            complete: function() {
                 $button.prop('disabled', false).html('<i class="fas fa-cog"></i> Save AI & Source Settings');
                setTimeout(() => aiGenerationStatusDiv.fadeOut(500, () => aiGenerationStatusDiv.addClass('hidden')), 7000);
            }
        });
    });

    generateAIBtn.on('click', function() {
        const $button = $(this);
        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Initiating...');
        aiGenerationStatusDiv.removeClass('hidden').css('background-color', '#1a202c').css('color', '#e2e8f0').html('Initiating question generation... <i class="fas fa-spinner fa-spin"></i>');

        const topicsData = [];
        for (let i = 1; i <= 3; i++) {
            const topicName = $(`#wiz_trivia_topic_${i}`).val().trim();
            if (topicName) {
                const sourceUrls = $(`#wiz_trivia_topic_${i}_source_urls`).val().split('\n').map(url => url.trim()).filter(url => url !== '');
                topicsData.push({ name: topicName, sources: sourceUrls });
            }
        }

        if (topicsData.length === 0) {
            aiGenerationStatusDiv.text('Please enter at least one topic name.').css('background-color', '#fb6340').css('color', 'white');
            $button.prop('disabled', false).html('<i class="fas fa-cogs"></i> Generate Questions with AI');
            return;
        }

        // Get current AI settings from form to pass to backend if they differ from saved (e.g. user changed provider but didn't hit "Save AI Settings" yet)
        const currentAiProvider = $('#wiz_trivia_ai_provider').val();
        const currentPrimaryBlogDomain = $('#wiz_trivia_primary_blog_domain').val(); // Pass this as an override
        const currentAllowExternal = $('#wiz_trivia_allow_external_sources').is(':checked'); // Pass this as an override
        const knowledgePreference = $('input[name="wiz_trivia_knowledge_source"]:checked').val();


        $.ajax({
            url: triviaAdminSettings.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wiz_initiate_ai_question_generation',
                generate_nonce: triviaAdminSettings.generateNonce,
                topics_data: JSON.stringify(topicsData),
                ai_provider: currentAiProvider, // Send current selection from form
                primary_blog_domain_override: currentPrimaryBlogDomain,
                allow_external_sources_override: currentAllowExternal ? '1' : '0',
                knowledge_preference: knowledgePreference
            },
            success: function(response) {
                if (response.success) {
                    aiGenerationStatusDiv.html(response.data.message + ' Starting to monitor job status... <i class="fas fa-spinner fa-spin"></i>').css('background-color', '#2dce89').css('color', 'white');
                    startMonitoringJobStatus();
                } else {
                    aiGenerationStatusDiv.text(response.data.message || 'Failed to initiate generation.').css('background-color', '#f5365c').css('color', 'white');
                    $button.prop('disabled', false).html('<i class="fas fa-cogs"></i> Generate Questions with AI');
                }
            },
            error: function(xhr, txt, err) {
                let msg = 'AJAX Error: ' + txt;
                try {
                    if (xhr.responseText) {
                        const json = JSON.parse(xhr.responseText);
                        if (json.data && json.data.message) msg = json.data.message;
                        else if (json.message) msg = json.message;
                    }
                } catch (e) {}
                aiGenerationStatusDiv.text(msg).css('background-color', '#f5365c').css('color', 'white');
                console.error("AJAX initiate generation error:", err, xhr.responseText);
                $button.prop('disabled', false).html('<i class="fas fa-cogs"></i> Generate Questions with AI');
            }
            // 'complete' for the button is handled by job monitoring start/failure
        });
    });

    function startMonitoringJobStatus() {
        if (jobStatusInterval) clearInterval(jobStatusInterval); // Clear existing interval if any

        fetchJobStatus(); // Fetch immediately
        jobStatusInterval = setInterval(fetchJobStatus, 10000); // Check every 10 seconds
        generateAIBtn.prop('disabled', true).html('<i class="fas fa-hourglass-half"></i> Processing...');
    }

    function fetchJobStatus() {
        $.ajax({
            url: triviaAdminSettings.ajaxUrl,
            method: 'POST',
            data: {
                action: 'wiz_get_ai_job_status',
                nonce: triviaAdminSettings.nonce // General admin nonce for status check
            },
            success: function(response) {
                if (response.success && response.data.jobs) {
                    let statusHtml = '<h4>Generation Job Status:</h4><ul>';
                    let allDone = response.data.all_done !== undefined ? response.data.all_done : true; // Default to true if undefined

                    if (response.data.jobs.length === 0 || (response.data.jobs.length === 1 && response.data.jobs[0].message)) {
                        statusHtml += `<li>${response.data.jobs[0]?.message || 'No active jobs or queue is empty.'}</li>`;
                         allDone = true; // Explicitly set allDone if no real jobs
                    } else {
                        response.data.jobs.forEach(job => {
                            statusHtml += `<li><strong>Topic:</strong> ${sanitizeInput(job.topic_name)} - <strong>Status:</strong> ${sanitizeInput(job.status)} 
                                           (Generated: ${job.generated_count}/${job.target_count})
                                           ${job.last_error ? `<br><small class="text-red-400">Error: ${sanitizeInput(job.last_error)}</small>` : ''}
                                        </li>`;
                            if (job.status === 'pending' || job.status === 'processing') {
                                allDone = false; // If any job is active, not all are done
                            }
                        });
                    }
                    statusHtml += '</ul>';
                    aiGenerationStatusDiv.html(statusHtml).removeClass('hidden');
                    if (allDone) {
                        clearInterval(jobStatusInterval);
                        jobStatusInterval = null;
                        aiGenerationStatusDiv.append('<p>All generation tasks are complete or no active jobs found.</p>');
                        generateAIBtn.prop('disabled', false).html('<i class="fas fa-cogs"></i> Generate Questions with AI');
                        // Reload data from server to reflect AI-generated questions
                        loadInitialDataFromServer();
                    }
                } else {
                    aiGenerationStatusDiv.html(response.data.message || 'Error fetching job status.').removeClass('hidden');
                    // Don't stop monitoring on a single fetch error, could be transient
                }
            },
            error: function(xhr, txt, err) {
                let msg = 'AJAX Error fetching job status: ' + txt;
                try {
                    if (xhr.responseText) {
                        const json = JSON.parse(xhr.responseText);
                        if (json.data && json.data.message) msg = json.data.message;
                         else if (json.message) msg = json.message;
                    }
                } catch (e) {}
                aiGenerationStatusDiv.html(msg).removeClass('hidden');
                console.error("AJAX fetch job status error:", err, xhr.responseText);
                // Consider whether to stop monitoring or retry after an error
            }
        });
    }

    function loadInitialDataFromServer() {
        // This function could re-fetch triviaAdminSettings.initialData if it's dynamically updated
        // For now, we assume the full page would be reloaded or data is manually synced.
        // A more robust way would be an AJAX call to get the latest triviaData.json content.
        // For simplicity here, we'll just show a message to refresh.
        // Or, if your save function returns the full data, update `triviaData` global var with it.
        // For now, just re-render based on current JS memory `triviaData` which might have been updated by AI cron.
        // A better approach is to actually fetch the JSON file again.
        $.getJSON(triviaAdminSettings.jsonDataUrl + '?v=' + new Date().getTime()) // Cache bust
            .done(function(data) {
                triviaData = data || {};
                renderQuestionsTable();
                populateTopicAndLevelDropdowns();
                showMessage('Data refreshed with latest AI generated questions (if any).', true, mainFormAreaTitle);
            })
            .fail(function(jqxhr, textStatus, error) {
                const err = textStatus + ", " + error;
                console.error("Error fetching latest trivia data: " + err);
                showMessage('Could not refresh data with AI questions. Please save manually or refresh page.', false, mainFormAreaTitle);
            });
    }


    // --- Initial Page Load ---
    renderQuestionsTable();
    populateTopicAndLevelDropdowns();
    clearForm(); // Set up form for new entry initially

    // Check for ongoing jobs on page load, but only if generateAIBtn exists
    if (generateAIBtn.length) {
        // Check if there might be ongoing jobs by looking at the transient (PHP would need to pass a flag or initial job list)
        // For now, we can just call fetchJobStatus once to see if anything is in the queue from a previous session.
        // But this requires the queue to persist across page loads and be cleared appropriately.
        // A simple way: if generateAIBtn is not disabled, assume no jobs are running from *this* session.
        // To detect jobs from *previous* sessions/runs, PHP would need to tell JS if the queue is active.
        // For now, let's assume monitoring only starts on explicit "Generate" click.
        // You could call fetchJobStatus() here if you have a way to know if jobs *should* be running.
    }

});