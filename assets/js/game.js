/**
 * WizTrivia Game JavaScript - Complete Version
 * Version: 2.2.0
 * Date: 2025-05-23 07:26:26
 * User: cyberkarhub
 */

jQuery(document).ready(function($) {
    console.log('WizTrivia game script loaded - ' + new Date().toISOString());
    
    // Game variables
    let currentQuestion = 0;
    let score = 0;
    let questions = [];
    let currentLevel = '';
    let currentTopic = '';
    let selectedOption = null;
    let levelData = {};
    let fiftyFiftyUsedThisLevel = false;
    let gameSettings = {};
    
    // Level tracking variables
    let correctAnswersInCurrentLevel = 0;
    let questionsInCurrentLevel = 0;
    let levelStartIndex = 0;
    
    // Track whether we're processing a level transition to prevent loops
    let isProcessingLevelTransition = false;
    
    // Timer variables
    let TIMER_DURATION = 30; // Default, will be overridden by settings
    let timeLeft = TIMER_DURATION;
    let timerInterval = null;
    
    // Game screens
    const welcomeScreen = $('#wiztrivia-welcome-screen');
    const topicScreen = $('#wiztrivia-topic-screen');
    const gameScreen = $('#wiztrivia-game-screen');
    const resultsScreen = $('#wiztrivia-results-screen');
    
    // Game elements
    const triviaContainer = $('.wiztrivia-container');
    const questionElement = $('#wiztrivia-question');
    const optionsContainer = $('#wiztrivia-options');
    const nextButton = $('#wiztrivia-next');
    const scoreElement = $('#wiztrivia-score');
    const levelElement = $('#wiztrivia-level');
    const timerElement = $('#wiztrivia-timer');
    const timerBarElement = $('#wiztrivia-timer-bar');
    const fiftyFiftyButton = $('#wiztrivia-fifty-fifty');
    const levelProgressBarElement = $('#level-progress-bar');
    const questionProgressElement = $('#question-progress');
    const feedbackElement = $('.wiztrivia-feedback');
    
    // Welcome Screen Elements
    const startButton = $('#wiztrivia-start-button');
    
    // Topic Screen Elements
    const topicButtons = $('.wiztrivia-topic-button');
    const backToWelcomeButton = $('#wiztrivia-back-to-welcome');
    
    // Results Screen Elements
    const finalScoreElement = $('#wiztrivia-final-score');
    const achievementElement = $('#wiztrivia-achievement');
    const articleRecommendationElement = $('#wiztrivia-article-recommendation');
    const playAgainButton = $('#wiztrivia-play-again');
    const homeButton = $('#wiztrivia-home');
    
    // Initialize game
    initGame();
    
    function initGame() {
        try {
            // Load game data
            if (typeof wizTriviaGameData !== 'undefined') {
                gameSettings = wizTriviaGameData.settings || {};
                levelData = { levels: wizTriviaGameData.levels || [] };
                
                // Update timer duration from settings
                if (gameSettings.timer_duration) {
                    TIMER_DURATION = parseInt(gameSettings.timer_duration);
                }
                
                console.log('WizTrivia: Loaded game settings', gameSettings);
                
                // Set game title
                if (gameSettings.game_title) {
                    $('#wiztrivia-game-title').text(gameSettings.game_title);
                }
            }
            
            // Set up welcome screen events
            startButton.on('click', showTopicScreen);
            
            // Set up topic screen events
            topicButtons.on('click', function() {
                const topic = $(this).data('topic');
                selectTopic(topic);
            });
            
            backToWelcomeButton.on('click', showWelcomeScreen);
            
            // Set up results screen events
            playAgainButton.on('click', showTopicScreen);
            homeButton.on('click', showWelcomeScreen);
            
            // Set up next button
            nextButton.on('click', nextQuestion);
            
            // Set up fifty-fifty button if enabled
            if (fiftyFiftyButton.length) {
                fiftyFiftyButton.on('click', useFiftyFifty);
            }
            
            // Start with welcome screen
            showWelcomeScreen();
            
        } catch (e) {
            console.error('WizTrivia: Error initializing game', e);
            showError('Error initializing game: ' + e.message);
        }
    }
    
    function showError(message) {
        console.error('WizTrivia Error:', message);
        if (questionElement) {
            questionElement.html('<span style="color:red;">' + message + '</span>');
        }
        if (nextButton) {
            nextButton.prop('disabled', true);
        }
    }
    
    function showWelcomeScreen() {
        welcomeScreen.show();
        topicScreen.hide();
        gameScreen.hide();
        resultsScreen.hide();
    }
    
    function showTopicScreen() {
        welcomeScreen.hide();
        topicScreen.show();
        gameScreen.hide();
        resultsScreen.hide();
    }
    
    function selectTopic(topic) {
        currentTopic = topic;
        
        // Filter questions for this topic
        if (typeof wizTriviaQuestions !== 'undefined' && wizTriviaQuestions.length > 0) {
            questions = wizTriviaQuestions.filter(function(q) {
                return q.topic === topic;
            });
            
            if (questions.length > 0) {
                console.log('WizTrivia: Loaded ' + questions.length + ' questions for topic: ' + topic);
                
                // Reset game state
                currentQuestion = 0;
                score = 0;
                correctAnswersInCurrentLevel = 0;
                fiftyFiftyUsedThisLevel = false;
                
                // Sort questions by difficulty
                const difficultyOrder = {'easy': 1, 'medium': 2, 'hard': 3, 'advanced': 4, 'expert': 5};
                questions.sort(function(a, b) {
                    return (difficultyOrder[a.difficulty] || 999) - (difficultyOrder[b.difficulty] || 999);
                });
                
                // Get the first question's properties
                currentLevel = questions[0].difficulty || 'easy';
                updateLevelDisplay();
                
                // Initialize level tracking
                levelStartIndex = 0;
                countQuestionsInCurrentLevel();
                
                // Show game screen and load first question
                topicScreen.hide();
                gameScreen.show();
                displayQuestion();
            } else {
                showError('No questions available for topic: ' + topic);
            }
        } else {
            showError('No questions available. Please add questions in the admin panel.');
        }
    }
    
    function updateLevelDisplay() {
        if (levelElement) {
            levelElement.text('Level: ' + (currentLevel.charAt(0).toUpperCase() + currentLevel.slice(1)));
        }
    }
    
    function countQuestionsInCurrentLevel() {
        questionsInCurrentLevel = 0;
        const currentLevelName = currentLevel;
        
        // Count questions with the same difficulty level
        for (let i = levelStartIndex; i < questions.length; i++) {
            if (questions[i].difficulty === currentLevelName) {
                questionsInCurrentLevel++;
            } else if (questionsInCurrentLevel > 0) {
                // Stop counting when we reach a different level (after finding some)
                break;
            }
        }
        
        // Limit to the maximum per level from settings
        const maxPerLevel = gameSettings.questions_per_level || 5;
        questionsInCurrentLevel = Math.min(questionsInCurrentLevel, maxPerLevel);
        
        console.log(`WizTrivia: Using ${questionsInCurrentLevel} questions in ${currentLevelName} level`);
    }
    
    function displayQuestion() {
        // Clear any existing timer
        clearInterval(timerInterval);
        
        if (currentQuestion >= questions.length) {
            // Game over
            showResults();
            return;
        }
        
        const question = questions[currentQuestion];
        console.log('WizTrivia: Displaying question ' + (currentQuestion + 1), question);
        
        // Check if we've moved to a new difficulty level AND we're not already processing a transition
        if (currentQuestion > 0 && 
            question.difficulty !== questions[currentQuestion-1].difficulty && 
            !isProcessingLevelTransition) {
            
            // We've completed the previous level, check if player answered enough correctly
            const previousLevel = questions[currentQuestion-1].difficulty;
            
            // Set flag to prevent recursion
            isProcessingLevelTransition = true;
            
            const requiredCorrect = gameSettings.correct_answers_required || 3;
            
            if (correctAnswersInCurrentLevel >= requiredCorrect) {
                // Player passed the level! Show success message and advance
                currentLevel = question.difficulty || 'easy';
                updateLevelDisplay();
                fiftyFiftyUsedThisLevel = false; // Reset fifty-fifty for new level
                
                // Reset level tracking for new level
                correctAnswersInCurrentLevel = 0;
                levelStartIndex = currentQuestion;
                countQuestionsInCurrentLevel();
                
                // Show level completion message
                const levelName = currentLevel.charAt(0).toUpperCase() + currentLevel.slice(1);
                showModal(`Level ${levelName} Unlocked!`, 
                        `Congratulations! You answered ${correctAnswersInCurrentLevel} questions correctly and advanced to the ${levelName} level.`,
                        'Continue', function() {
                            // Reset flag when modal is closed and THEN proceed with question
                            isProcessingLevelTransition = false;
                            loadQuestionContent();
                        }, findLevelArticleUrl(currentLevel));
            } else {
                // Player failed the level! Show failure message and offer retry
                showModal(`Level Challenge`, 
                        `You answered only ${correctAnswersInCurrentLevel} out of ${questionsInCurrentLevel} questions correctly. You need at least ${requiredCorrect} correct answers to advance.`,
                        'Retry Level', function() {
                            // Reset to beginning of this level
                            currentQuestion = levelStartIndex;
                            correctAnswersInCurrentLevel = 0;
                            fiftyFiftyUsedThisLevel = false;
                            isProcessingLevelTransition = false;
                            loadQuestionContent();
                        });
            }
            
            return; // Wait for modal to close before showing question
        }
        
        // If we reach here, just load the question content directly
        loadQuestionContent();
    }
    
    function loadQuestionContent() {
        const question = questions[currentQuestion];
        
        // Ensure essential UI containers are visible
        gameScreen.show();
        
        // Reset UI elements
        optionsContainer.empty();
        questionElement.text(question.question);
        feedbackElement.addClass('hidden');
        
        // Enable/disable fifty-fifty button
        if (fiftyFiftyButton.length) {
            fiftyFiftyButton.prop('disabled', fiftyFiftyUsedThisLevel);
        }
        
        // Get options
        const correctAnswer = question.correct_answer;
        const incorrectAnswers = question.incorrect_answers || [];
        
        if (!correctAnswer) {
            showError('Question is missing correct answer');
            return;
        }
        
        // Combine all options
        const allOptions = [correctAnswer, ...incorrectAnswers];
        
        // Shuffle options
        shuffleArray(allOptions);
        
        // Add options to the container
        allOptions.forEach(function(option) {
            const optionElement = $('<div class="wiztrivia-option"></div>').text(option);
            optionElement.on('click', function() {
                selectOption($(this), option === correctAnswer);
            });
            optionsContainer.append(optionElement);
        });
        
        // Update score
        scoreElement.text('Score: ' + score);
        
        // Update level progress
        updateLevelProgress();
        
        // Hide next button
        nextButton.addClass('hidden');
        
        // Start timer
        startTimer();
    }
    
    function updateLevelProgress() {
        if (!levelProgressBarElement.length || !questionProgressElement.length) return;
        
        // Calculate current position in level
        const currentPositionInLevel = currentQuestion - levelStartIndex + 1;
        
        // Update progress text
        questionProgressElement.text(`${currentPositionInLevel}/${questionsInCurrentLevel}`);
        
        // Update progress bar
        const progressPercent = (currentPositionInLevel / questionsInCurrentLevel) * 100;
        levelProgressBarElement.css('width', `${progressPercent}%`);
    }
    
    function startTimer() {
        timeLeft = TIMER_DURATION;
        updateTimerDisplay();
        
        timerInterval = setInterval(function() {
            timeLeft--;
            updateTimerDisplay();
            
            if (timeLeft <= 0) {
                timeUp();
            }
        }, 1000);
    }
    
    function updateTimerDisplay() {
        if (!timerElement.length || !timerBarElement.length) return;
        
        // Update timer text
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        timerElement.text(`${minutes}:${seconds < 10 ? '0' : ''}${seconds}`);
        
        // Update timer bar
        const timerPercent = (timeLeft / TIMER_DURATION) * 100;
        timerBarElement.css('width', `${timerPercent}%`);
        
        // Add warning color when time is low
        if (timeLeft <= 5) {
            timerBarElement.addClass('time-warning');
        } else {
            timerBarElement.removeClass('time-warning');
        }
    }
    
    function timeUp() {
        clearInterval(timerInterval);
        
        // Disable options
        optionsContainer.find('.wiztrivia-option').prop('disabled', true);
        
        // Show correct answer
        const question = questions[currentQuestion];
        optionsContainer.find('.wiztrivia-option').each(function() {
            if ($(this).text() === question.correct_answer) {
                $(this).addClass('correct-answer');
            }
        });
        
        // Show feedback with source link if available
        let feedbackText = "Time's up! The correct answer was: " + question.correct_answer;
        
        // Add source link if available
        if (question.source) {
            feedbackText += ' <a href="' + question.source + '" target="_blank" class="wiztrivia-source-link">View Source</a>';
        }
        
        if (feedbackElement.length) {
            feedbackElement.html(feedbackText);
            feedbackElement.removeClass('correct').addClass('incorrect');
            feedbackElement.removeClass('hidden');
        }
        
        // Enable next button
        nextButton.prop('disabled', false);
        nextButton.removeClass('hidden');
        
        // Set focus to next button
        setTimeout(() => {
            try {
                nextButton.focus();
            } catch (e) {
                console.warn("Error focusing Next button:", e);
            }
        }, 50);
    }
    
    function selectOption(optionElement, isCorrect) {
        // Stop the timer
        clearInterval(timerInterval);
        
        // If already selected an option, do nothing
        if (selectedOption !== null) {
            return;
        }
        
        selectedOption = optionElement;
        
        // Disable all options
        optionsContainer.find('.wiztrivia-option').prop('disabled', true);
        
        // Get current question for source link
        const question = questions[currentQuestion];
        
        // Highlight correct/incorrect
        if (isCorrect) {
            optionElement.addClass('correct-answer');
            score += 10;
            correctAnswersInCurrentLevel++; // Increment correct answers for this level
            
            // Show feedback
            if (feedbackElement.length) {
                let feedbackText = "Correct!";
                
                // Add source link if available
                if (question.source) {
                    feedbackText += ' <a href="' + question.source + '" target="_blank" class="wiztrivia-source-link">Learn More</a>';
                }
                
                feedbackElement.html(feedbackText);
                feedbackElement.removeClass('incorrect').addClass('correct');
                feedbackElement.removeClass('hidden');
            }
        } else {
            optionElement.addClass('incorrect-answer');
            
            // Show which one was correct
            optionsContainer.find('.wiztrivia-option').each(function() {
                if ($(this).text() === question.correct_answer) {
                    $(this).addClass('correct-answer');
                }
            });
            
            // Show feedback with source link
            if (feedbackElement.length) {
                let feedbackText = "Incorrect. The correct answer was: " + question.correct_answer;
                
                // Add source link if available
                if (question.source) {
                    feedbackText += ' <a href="' + question.source + '" target="_blank" class="wiztrivia-source-link">Learn More</a>';
                }
                
                feedbackElement.html(feedbackText);
                feedbackElement.removeClass('correct').addClass('incorrect');
                feedbackElement.removeClass('hidden');
            }
        }
        
        // Update score
        scoreElement.text('Score: ' + score);
        
        // Enable next button
        nextButton.prop('disabled', false);
        nextButton.removeClass('hidden');
        
        // Set focus to next button
        setTimeout(() => {
            try {
                nextButton.focus();
            } catch (e) {
                console.warn("Error focusing Next button:", e);
            }
        }, 50);
    }
    
    function nextQuestion() {
        currentQuestion++;
        selectedOption = null; // Reset selected option
        nextButton.addClass('hidden');
        
        // Check if this was the last question in the level
        const isLastQuestionInLevel = (currentQuestion - levelStartIndex >= questionsInCurrentLevel) || 
                                    (currentQuestion < questions.length && 
                                     questions[currentQuestion].difficulty !== currentLevel);
        
        if (isLastQuestionInLevel) {
            // Level is complete, check if player passed
            const requiredCorrect = gameSettings.correct_answers_required || 3;
            
            if (correctAnswersInCurrentLevel >= requiredCorrect) {
                // Player passed! Show success message
                showModal(`Level Complete!`, 
                         `You answered ${correctAnswersInCurrentLevel} out of ${questionsInCurrentLevel} questions correctly. You've cleared the ${currentLevel} level!`,
                         'Continue', function() {
                             // Continue to next level
                             displayQuestion();
                         });
            } else {
                // Player failed! Show failure message
                showModal(`Level Failed`, 
                         `You answered only ${correctAnswersInCurrentLevel} out of ${questionsInCurrentLevel} questions correctly. You need at least ${requiredCorrect} correct answers to advance.`,
                         'Retry Level', function() {
                             // Reset to beginning of this level
                             currentQuestion = levelStartIndex;
                             correctAnswersInCurrentLevel = 0;
                             fiftyFiftyUsedThisLevel = false;
                             displayQuestion();
                         });
            }
        } else {
            // Not the last question, just display next question
            displayQuestion();
        }
    }
    
    function useFiftyFifty() {
        if (fiftyFiftyUsedThisLevel) return;
        
        const question = questions[currentQuestion];
        const correctAnswer = question.correct_answer;
        
        // Get all incorrect options
        const incorrectOptions = optionsContainer.find('.wiztrivia-option').filter(function() {
            return $(this).text() !== correctAnswer;
        }).toArray();
        
        // Randomly shuffle incorrect options
        shuffleArray(incorrectOptions);
        
        // Hide all but one incorrect option (keep the correct one and one incorrect)
        for (let i = 0; i < incorrectOptions.length - 1; i++) {
            $(incorrectOptions[i]).addClass('hidden');
            $(incorrectOptions[i]).prop('disabled', true);
        }
        
        // Mark as used for this level
        fiftyFiftyUsedThisLevel = true;
        if (fiftyFiftyButton.length) {
            fiftyFiftyButton.prop('disabled', true);
        }
    }
    
    function findLevelArticleUrl(levelName) {
        if (!levelData || !levelData.levels) return null;
        
        for (let i = 0; i < levelData.levels.length; i++) {
            if (levelData.levels[i].name && levelData.levels[i].name.toLowerCase() === levelName.toLowerCase() && 
                levelData.levels[i].article_url) {
                return {
                    url: levelData.levels[i].article_url,
                    title: levelData.levels[i].article_title || (levelData.levels[i].name + " Level Resources")
                };
            }
        }
        return null;
    }
    
    function showResults() {
        // Hide game screen
        gameScreen.hide();
        
        // Show results screen
        resultsScreen.show();
        
        // Display final score
        const maxScore = questions.length * 10;
        finalScoreElement.html(`<p>Your final score: <strong>${score}</strong> out of ${maxScore}</p>`);
        
        // Find highest level achieved based on score
        if (levelData && levelData.levels && levelData.levels.length) {
            let highestLevelAchieved = null;
            
            for (let i = levelData.levels.length - 1; i >= 0; i--) {
                if (score >= levelData.levels[i].required_score) {
                    highestLevelAchieved = levelData.levels[i];
                    break;
                }
            }
            
            if (highestLevelAchieved) {
                // Show achievement
                achievementElement.html(`<p>You've achieved the <strong>${highestLevelAchieved.name}</strong> level!</p>`);
                
                // Show article link if available
                if (highestLevelAchieved.article_url) {
                    articleRecommendationElement.html(`
                        <p>Continue learning with this article:</p>
                        <a href="${highestLevelAchieved.article_url}" target="_blank" class="wiztrivia-article-link">
                            ${highestLevelAchieved.article_title || (highestLevelAchieved.name + " Resources")}
                        </a>
                    `);
                } else {
                    articleRecommendationElement.empty();
                }
            }
        }
        
        // Set focus to play again button
        setTimeout(() => {
            try {
                playAgainButton.focus();
            } catch (e) {
                console.warn("Error focusing play again button:", e);
            }
        }, 50);
    }
    
    function showModal(title, message, buttonText, callback, articleDetails) {
        // Create modal if it doesn't exist
        if ($('#wiztrivia-modal').length === 0) {
            $('body').append(`
                <div id="wiztrivia-modal" class="wiztrivia-modal">
                    <div class="wiztrivia-modal-content">
                        <h3 id="wiztrivia-modal-title"></h3>
                        <div id="wiztrivia-modal-body"></div>
                        <div id="wiztrivia-modal-article" class="wiztrivia-article-suggestion"></div>
                        <div class="wiztrivia-modal-footer">
                            <button id="wiztrivia-modal-continue" class="wiztrivia-button"></button>
                        </div>
                    </div>
                </div>
            `);
        }
        
        // Set content
        $('#wiztrivia-modal-title').text(title);
        $('#wiztrivia-modal-body').html(message);
        
        // Add article link if provided
        if (articleDetails && articleDetails.url) {
            $('#wiztrivia-modal-article').html(`
                <p>Want to learn more? Check out this article:</p>
                <a href="${articleDetails.url}" target="_blank" 
                   class="wiztrivia-article-link">${articleDetails.title || "Learn More"}</a>
            `).show();
        } else {
            $('#wiztrivia-modal-article').hide();
        }
        
        $('#wiztrivia-modal-continue').text(buttonText || 'Continue');
        
        // Show modal
        $('#wiztrivia-modal').fadeIn(300);
        
        // Set focus with a small delay to ensure the element is properly rendered
        setTimeout(() => {
            try {
                $('#wiztrivia-modal-continue').focus();
            } catch (e) {
                console.warn("Error focusing modal button:", e);
            }
        }, 50);
        
        // Button click handler
        $('#wiztrivia-modal-continue').off('click').on('click', function() {
            $('#wiztrivia-modal').fadeOut(300);
            if (typeof callback === 'function') {
                setTimeout(() => {
                    callback(); // Use setTimeout to ensure modal is fully hidden first
                }, 350);
            }
        });
    }
    
    // Utility function to shuffle array
    function shuffleArray(array) {
        for (let i = array.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [array[i], array[j]] = [array[j], array[i]];
        }
    }
});