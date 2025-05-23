// --- game.js - With Targeted Fix for UI Stability During Modals ---

// --- Global Variables ---
let triviaData = {};
let currentTopic = null;
let currentLevelIndex = 0;
let currentQuestionIndex = 0;
let score = 0;
let totalQuestionsInTopic = 0;
let correctAnswersInLevel = 0;
const TIMER_DURATION = 20;
let timeLeft = TIMER_DURATION;
let timerInterval = null;
let fiftyFiftyLifelinesUsedThisLevel = false;

// --- DOM Element References ---
const preloadScreen = document.getElementById('preload-screen');
const gameArea = document.getElementById('game-area');
const gameHeader = document.getElementById('game-header');
const levelNumberElement = document.getElementById('level-number');
const scoreNumberElement = document.getElementById('score-number');
const exitButton = document.getElementById('exit-button');
const homeButton = document.getElementById('home-button');
const questionArea = document.getElementById('question-area');
const questionElement = document.getElementById('question');
const optionsElement = document.getElementById('options');
const feedbackFooter = document.getElementById('feedback-footer');
const feedbackElement = document.getElementById('feedback');
const gameFooter = document.getElementById('game-footer');
const endGameArea = document.getElementById('end-game-area');
const finalScoreDisplay = document.getElementById('final-score');
const startGameButton = document.getElementById('start-game-button');
const mainNextButton = document.getElementById('main-next-button');
const restartButton = document.getElementById('restart-button');
const timerDisplayElement = document.getElementById('timer-display');
const lifelineFiftyFiftyButton = document.getElementById('lifeline-5050');
const questionProgressDisplayElement = document.getElementById('question-progress-display')?.querySelector('.question-progress-number');
const levelProgressBarElement = document.getElementById('level-progress-bar');

const messageBox = document.getElementById('message-box');
const loadMessageContent = messageBox?.querySelector('.load-message-content');
const loadMessageText = loadMessageContent?.querySelector('#load-message-text');
const exitMessageContent = messageBox?.querySelector('.exit-message-content');
const exitMessageText = exitMessageContent?.querySelector('#exit-message-text');
const levelCompleteMessageContent = messageBox?.querySelector('.level-complete-message-content');
const levelCompleteTitle = levelCompleteMessageContent?.querySelector('#level-complete-title');
const levelCompleteText = levelCompleteMessageContent?.querySelector('#level-complete-text');
const homeMessageContent = messageBox?.querySelector('.home-message-content');
const homeMessageText = homeMessageContent?.querySelector('#home-message-text');
const newLevelMessageContent = messageBox?.querySelector('.new-level-message-content');
const newLevelTitleElement = newLevelMessageContent?.querySelector('#new-level-title');
const newLevelMainText = newLevelMessageContent?.querySelector('#new-level-main-text');
const newLevelArticleSuggestion = newLevelMessageContent?.querySelector('#new-level-article-suggestion');
const newLevelArticleLink = newLevelMessageContent?.querySelector('#new-level-article-link');
const retryLevelMessageContent = messageBox?.querySelector('.retry-level-message-content');
const retryLevelTitleElement = retryLevelMessageContent?.querySelector('#retry-level-title');
const retryLevelText = retryLevelMessageContent?.querySelector('#retry-level-text');
const genericInfoContent = messageBox?.querySelector('.generic-info-content');
const genericInfoText = genericInfoContent?.querySelector('#generic-info-text');

const closeLoadMessageButton = loadMessageContent?.querySelector('#close-load-message-button');
const saveExitButton = exitMessageContent?.querySelector('#save-exit-button');
const exitWithoutSaveButton = exitMessageContent?.querySelector('#exit-without-save-button');
const cancelExitButton = exitMessageContent?.querySelector('#cancel-exit-button');
const continueButton = levelCompleteMessageContent?.querySelector('#continue-button');
const confirmHomeButton = homeMessageContent?.querySelector('#confirm-home-button');
const cancelHomeButton = homeMessageContent?.querySelector('#cancel-home-button');
const newLevelContinueButton = newLevelMessageContent?.querySelector('#new-level-continue-button');
const retryLevelButton = retryLevelMessageContent?.querySelector('#retry-level-button');
const retryMenuButton = retryLevelMessageContent?.querySelector('#retry-menu-button');
const genericInfoOkButton = genericInfoContent?.querySelector('#generic-info-ok-button');

const topicSelectionLogo = document.getElementById('topic-selection-logo');
const preloadGameTitle = preloadScreen?.querySelector('.game-title');
const welcomeParagraph = document.getElementById('welcome-paragraph');
const chooseTopicHeading = document.getElementById('choose-topic-heading');
const topicOptionsPreloadContainer = document.getElementById('topic-options-preload');

const allModalContentPanes = [
    loadMessageContent, exitMessageContent, levelCompleteMessageContent,
    homeMessageContent, newLevelMessageContent, retryLevelMessageContent, genericInfoContent
];

// --- Utility Functions ---
function adjustMainContentPadding() {}

function getTotalQuestionsEncounteredInTopic() {
    let encountered = 0;
    if (!triviaData || !currentTopic || !triviaData[currentTopic] || !Array.isArray(triviaData[currentTopic])) return 0;
    const topicLevels = triviaData[currentTopic];
    for (let i = 0; i < currentLevelIndex; i++) {
        if (topicLevels[i]?.questions?.length) {
            encountered += topicLevels[i].questions.length;
        }
    }
    encountered += currentQuestionIndex;
    return encountered;
}

function calculateTotalQuestionsInTopic() {
    let total = 0;
    if (!triviaData || !currentTopic || !triviaData[currentTopic] || !Array.isArray(triviaData[currentTopic])) return 0;
    const topicLevels = triviaData[currentTopic];
    topicLevels.forEach(level => {
        if (level?.questions?.length) {
            total += level.questions.length;
        }
    });
    return total;
}

// --- Game State Management ---
function saveGameState() {
    try {
        const gameState = {
            topic: currentTopic, level: currentLevelIndex, question: currentQuestionIndex, score: score,
            correctAnswersInLevel: correctAnswersInLevel, fiftyFiftyUsedThisLevel: fiftyFiftyLifelinesUsedThisLevel
        };
        localStorage.setItem('generativeAiTriviaState', JSON.stringify(gameState));
    } catch (e) { console.error("Error saving game state:", e); }
}

function loadGameState() {
    const savedStateRaw = localStorage.getItem('generativeAiTriviaState');
    if (!savedStateRaw) { showPreloadScreen(); return; }
    try {
        const savedState = JSON.parse(savedStateRaw);
        if (!savedState || typeof savedState !== 'object' || !savedState.topic || !triviaData || typeof triviaData !== 'object' || !triviaData[savedState.topic]) {
            clearGameState(); showPreloadScreen(); return;
        }
        currentTopic = savedState.topic; currentLevelIndex = savedState.level || 0; currentQuestionIndex = savedState.question || 0;
        score = savedState.score || 0; correctAnswersInLevel = savedState.correctAnswersInLevel || 0;
        fiftyFiftyLifelinesUsedThisLevel = savedState.fiftyFiftyUsedThisLevel || false;
        totalQuestionsInTopic = calculateTotalQuestionsInTopic();
        const currentLevelData = triviaData[currentTopic]?.[currentLevelIndex];
        const currentQuestionData = currentLevelData?.questions?.[currentQuestionIndex];
        if (!currentLevelData || !currentQuestionData) {
            currentLevelIndex = 0; currentQuestionIndex = 0; correctAnswersInLevel = 0; score = 0; fiftyFiftyLifelinesUsedThisLevel = false;
        }
        if (topicSelectionLogo) topicSelectionLogo.classList.add('hidden');
        if (preloadGameTitle) preloadGameTitle.classList.add('hidden');
        if (welcomeParagraph) welcomeParagraph.classList.add('hidden');
        if (startGameButton) startGameButton.classList.add('hidden');
        if (chooseTopicHeading) chooseTopicHeading.classList.add('hidden');
        if (topicOptionsPreloadContainer) topicOptionsPreloadContainer.classList.add('hidden');
        showGameArea(); loadQuestion();
        showMessageBoxContent('load', 'Game progress loaded successfully.');
    } catch (e) { console.error("Error parsing saved game state:", e); clearGameState(); showPreloadScreen(); }
}

function clearGameState() {
    try { localStorage.removeItem('generativeAiTriviaState'); }
    catch (e) { console.error("Error clearing game state:", e); }
}

// --- UI Control Functions ---
// In game.js
function showMessageBoxContent(contentType, message = '', title = '', articleDetails = null) {
    if (!messageBox) { console.error("Message Box container (#message-box) NOT FOUND."); return; }
    if (lifelineFiftyFiftyButton) lifelineFiftyFiftyButton.disabled = true;

    allModalContentPanes.forEach(pane => {
        if (pane && pane.classList) {
            pane.classList.remove('visible');
        }
    });

    let targetContentPane = null;
    let focusButton = null;
    let shouldSetFocus = true; // Flag to control if focus should be set

    switch (contentType) {
        case 'load':
            if (loadMessageText) loadMessageText.textContent = message;
            targetContentPane = loadMessageContent;
            focusButton = closeLoadMessageButton;
            // For 'load' messages that might appear on page load,
            // and are often auto-dismissed or informational,
            // you might decide not to steal focus immediately.
            // Consider uncommenting the line below if the 'load' modal still causes issues with initial page interactions:
            // shouldSetFocus = false; 
            break;
        case 'exit':
            if (exitMessageText) exitMessageText.textContent = message;
            targetContentPane = exitMessageContent;
            focusButton = saveExitButton; // Or cancelExitButton if that's more common as default
            break;
        case 'levelComplete':
            if (levelCompleteTitle) levelCompleteTitle.textContent = title;
            if (levelCompleteText) levelCompleteText.textContent = message;
            targetContentPane = levelCompleteMessageContent;
            focusButton = continueButton;
            break;
        case 'home':
            if (homeMessageText) homeMessageText.textContent = message;
            targetContentPane = homeMessageContent;
            focusButton = confirmHomeButton;
            break;
        case 'newLevel':
            if (newLevelTitleElement) newLevelTitleElement.textContent = title || `Level ${currentLevelIndex + 1} Unlocked!`;
            if (newLevelMainText) newLevelMainText.textContent = message || `Get ready for the next challenge!`;
            if (newLevelArticleSuggestion && newLevelArticleLink) {
                if (articleDetails && articleDetails.url && articleDetails.title) {
                    newLevelArticleSuggestion.textContent = "Want a sneak peek? Check out this related article:";
                    newLevelArticleLink.href = articleDetails.url; newLevelArticleLink.textContent = articleDetails.title;
                    newLevelArticleLink.classList.remove('hidden');
                } else {
                    newLevelArticleSuggestion.textContent = "More challenges await!";
                    newLevelArticleLink.classList.add('hidden');
                }
            }
            targetContentPane = newLevelMessageContent;
            focusButton = newLevelContinueButton;
            break;
        case 'retryLevel':
            if (retryLevelTitleElement) retryLevelTitleElement.textContent = title || `Level ${currentLevelIndex + 1} Challenge`;
            if (retryLevelText) retryLevelText.textContent = message;
            targetContentPane = retryLevelMessageContent;
            focusButton = retryLevelButton;
            break;
        case 'genericInfo':
            if (genericInfoText) genericInfoText.textContent = message;
            targetContentPane = genericInfoContent;
            focusButton = genericInfoOkButton;
            break;
        default:
            if (messageBox) messageBox.classList.add('hidden'); // Ensure messageBox is defined before adding class
            return;
    }

    if (targetContentPane && targetContentPane.classList) {
        targetContentPane.classList.add('visible');
        if (messageBox) messageBox.classList.remove('hidden'); // Ensure messageBox is defined

        if (shouldSetFocus && focusButton && typeof focusButton.focus === 'function') {
            setTimeout(() => {
                try { // Add try-catch for safety during focus attempt
                    focusButton.focus();
                } catch (e) {
                    console.warn("Error attempting to focus button:", focusButton, e);
                }
            }, 50); // Small timeout to ensure the element is rendered and focusable
        }
    } else {
        if (messageBox) messageBox.classList.add('hidden'); // Ensure messageBox is defined
    }
}

function hideMessageBox() {
    if (messageBox) messageBox.classList.add('hidden');
    allModalContentPanes.forEach(pane => { if (pane && pane.classList) pane.classList.remove('visible'); });
    if (gameArea && !gameArea.classList.contains('hidden') && mainNextButton && mainNextButton.classList.contains('hidden')) {
        const currentLevelObj = triviaData[currentTopic]?.[currentLevelIndex];
        const currentQuestionData = currentLevelObj?.questions?.[currentQuestionIndex];
        if (currentQuestionData && lifelineFiftyFiftyButton) {
            lifelineFiftyFiftyButton.disabled = fiftyFiftyLifelinesUsedThisLevel || (currentQuestionData.options?.length <= 2);
        }
    }
}

function showPreloadScreen() {
    if (gameArea) gameArea.classList.add('hidden'); if (gameHeader) gameHeader.classList.add('hidden');
    if (gameFooter) gameFooter.classList.add('hidden');
    if (feedbackFooter) { feedbackFooter.classList.add('hidden'); feedbackFooter.classList.remove('correct-state', 'incorrect-state'); }
    if (preloadScreen) preloadScreen.classList.remove('hidden');
    if (topicSelectionLogo) topicSelectionLogo.classList.add('hidden');
    if (preloadGameTitle) preloadGameTitle.classList.remove('hidden');
    if (welcomeParagraph) welcomeParagraph.classList.remove('hidden');
    if (startGameButton) startGameButton.classList.remove('hidden');
    if (chooseTopicHeading) chooseTopicHeading.classList.add('hidden');
    if (topicOptionsPreloadContainer) { topicOptionsPreloadContainer.classList.add('hidden'); topicOptionsPreloadContainer.innerHTML = ''; }
    if (questionArea && !questionArea.classList.contains('hidden')) questionArea.classList.add('hidden');
    hideMessageBox();
}

function showGameArea() {
    if (preloadScreen) preloadScreen.classList.add('hidden'); if (gameHeader) gameHeader.classList.remove('hidden');
    if (gameArea) gameArea.classList.remove('hidden');
    if (questionArea) questionArea.classList.remove('hidden');
    adjustMainContentPadding(); hideMessageBox();
}

function startTimer() {
    clearInterval(timerInterval); timeLeft = TIMER_DURATION; updateTimerDisplay();
    timerInterval = setInterval(() => { timeLeft--; updateTimerDisplay(); if (timeLeft <= 0) timeUp(); }, 1000);
}

function updateTimerDisplay() {
    if (!timerDisplayElement) return;
    const m = Math.floor(timeLeft / 60), s = timeLeft % 60;
    timerDisplayElement.textContent = `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
}

function timeUp() {
    clearInterval(timerInterval);
    if (lifelineFiftyFiftyButton) lifelineFiftyFiftyButton.disabled = true;

    const levelObj = triviaData[currentTopic]?.[currentLevelIndex];
    const questionData = levelObj?.questions?.[currentQuestionIndex];

    if (questionData && feedbackElement && optionsElement) {
        let feedbackMsg = `Time's up! The correct answer was: <strong>${questionData.answer}</strong>.`;
        if (questionData.sourceUrl && questionData.sourceTitle) {
            feedbackMsg += ` <a href="${questionData.sourceUrl}" target="_blank" class="source-link">Learn more: ${questionData.sourceTitle}</a>`;
        }
        feedbackElement.innerHTML = feedbackMsg;

        Array.from(optionsElement.children).forEach(button => {
            button.disabled = true;
            if (button.textContent === questionData.answer) {
                button.classList.add('correct-answer');
            }
        });
    } else {
        if (feedbackElement) feedbackElement.innerHTML = "Time's up! Error retrieving answer details.";
        if (optionsElement) Array.from(optionsElement.children).forEach(b => { b.disabled = true; });
    }

    if (feedbackElement) feedbackElement.classList.add('incorrect'); // Or a neutral "time-up" style
    if (feedbackFooter) {
        feedbackFooter.classList.add('incorrect-state'); // Or a neutral state class
        feedbackFooter.classList.remove('correct-state', 'hidden');
    }

    if (mainNextButton) {
        mainNextButton.classList.remove('hidden');
        // SET FOCUS TO THE NEXT QUESTION BUTTON
        setTimeout(() => { // Use a small timeout
            if (mainNextButton && typeof mainNextButton.focus === 'function') {
                try {
                    mainNextButton.focus();
                } catch (e) {
                    console.warn("Error focusing Next Question button in timeUp:", e);
                }
            }
        }, 50);
    }
    if (scoreNumberElement) scoreNumberElement.textContent = `${score}/${getTotalQuestionsEncounteredInTopic() + 1}`; // +1 because this question is now "encountered"
    saveGameState();
}

function useFiftyFiftyLifeline() {
    if (fiftyFiftyLifelinesUsedThisLevel || !optionsElement) return;
    const qData = triviaData[currentTopic]?.[currentLevelIndex]?.questions?.[currentQuestionIndex];
    if (!qData || !qData.answer || !qData.options || qData.options.length <= 2) { if (lifelineFiftyFiftyButton) lifelineFiftyFiftyButton.disabled = true; return; }
    const correctAns = qData.answer;
    const incorrectOpts = Array.from(optionsElement.children).filter(b => b.textContent !== correctAns);
    incorrectOpts.sort(() => 0.5 - Math.random());
    let removedCount = 0;
    for (let i = 0; i < incorrectOpts.length && removedCount < 2 ; i++) {
        if (incorrectOpts[i]) {
            incorrectOpts[i].classList.add('hidden-by-lifeline'); incorrectOpts[i].disabled = true;
            removedCount++;
        }
    }
    fiftyFiftyLifelinesUsedThisLevel = true; if (lifelineFiftyFiftyButton) lifelineFiftyFiftyButton.disabled = true;
    saveGameState();
}

function updateLevelProgressVisuals() {
    if (!currentTopic || !triviaData[currentTopic]?.[currentLevelIndex]) return;
    const levelObj = triviaData[currentTopic][currentLevelIndex];
    if (!levelObj?.questions || !questionProgressDisplayElement || !levelProgressBarElement) return;
    const totalQ = levelObj.questions.length, currentQNum = currentQuestionIndex + 1;
    if (totalQ > 0) {
        questionProgressDisplayElement.textContent = `${currentQNum}/${totalQ}`;
        levelProgressBarElement.style.width = `${(currentQNum / totalQ) * 100}%`;
    } else { questionProgressDisplayElement.textContent = `0/0`; levelProgressBarElement.style.width = '0%'; }
}

function selectTopic(topicName) {
    clearGameState(); currentTopic = topicName; currentLevelIndex = 0; currentQuestionIndex = 0;
    score = 0; correctAnswersInLevel = 0; fiftyFiftyLifelinesUsedThisLevel = false;
    if (!triviaData || !triviaData[currentTopic] || !Array.isArray(triviaData[currentTopic]) || triviaData[currentTopic].length === 0) {
        showMessageBoxContent('genericInfo', `Sorry, no questions available for "${topicName}".`, "Topic Error");
        showPreloadScreen();
        if (startGameButton && chooseTopicHeading && topicOptionsPreloadContainer) {
            if(preloadGameTitle) preloadGameTitle.classList.add('hidden');
            if(welcomeParagraph) welcomeParagraph.classList.add('hidden');
            startGameButton.classList.add('hidden');
            chooseTopicHeading.classList.remove('hidden');
            topicOptionsPreloadContainer.classList.remove('hidden');
        }
        return;
    }
    totalQuestionsInTopic = calculateTotalQuestionsInTopic();
    showGameArea(); loadQuestion();
}

// ====================================================================================
// MODIFIED loadQuestion FUNCTION STARTS HERE
// ====================================================================================
function loadQuestion() {
    clearInterval(timerInterval);

    // Ensure essential UI containers are visible if they were hidden by other processes
    if (questionArea && questionArea.classList.contains('hidden')) {
        questionArea.classList.remove('hidden');
    }
    // showGameArea() should ideally handle gameArea visibility, but this is a safeguard.
    if (gameArea && gameArea.classList.contains('hidden')) {
         if (preloadScreen) preloadScreen.classList.add('hidden');
         if (gameHeader) gameHeader.classList.remove('hidden');
         gameArea.classList.remove('hidden');
    }

    // Reset footers and next button. These are reset whether we show a modal or a new question.
    if (feedbackElement) { feedbackElement.innerHTML = ''; feedbackElement.className = 'feedback-text'; }
    if (mainNextButton) mainNextButton.classList.add('hidden');
    if (gameFooter) gameFooter.classList.add('hidden'); // Hide end game footer
    if (feedbackFooter) { // Hide feedback footer from previous question
        feedbackFooter.classList.add('hidden');
        feedbackFooter.classList.remove('correct-state', 'incorrect-state');
    }

    if (!currentTopic || !triviaData[currentTopic]) {
        restartGame(); return;
    }
    const levels = triviaData[currentTopic];

    if (currentLevelIndex >= levels.length) {
        endGame(); // All levels for the topic are complete
        return; // Exit before clearing UI for the *last* question
    }

    const levelObj = levels[currentLevelIndex];

    if (!levelObj || !levelObj.questions || !Array.isArray(levelObj.questions) || levelObj.questions.length === 0) {
        proceedToNextLevelFlow(true); // true = wasLevelSkipped (or trying to skip an empty/invalid level)
        return; // Exit before clearing UI, let proceedToNextLevelFlow handle modal or next level attempt
    }

    if (currentQuestionIndex >= levelObj.questions.length) {
        proceedToNextLevelFlow(false); // false = finishing a non-empty level, show modal
        return; // Exit before clearing UI, modal will overlay current screen
    }

    // ----- If we've reached THIS POINT, we are ACTUALLY loading a NEW question's content. -----
    // ----- It's now safe to clear the previous question's specific elements. -----
    if (optionsElement) optionsElement.innerHTML = ''; // Clear previous options
    if (questionElement) questionElement.textContent = ''; // Clear previous question text

    const qData = levelObj.questions[currentQuestionIndex];
    if (!qData || !qData.question || !qData.options || qData.options.length < 2 || !qData.answer) {
        currentQuestionIndex++;
        loadQuestion(); // Try to load the next, next question (recursive call, be mindful of stack)
        return;
    }

    // Populate UI with the new question
    if (levelNumberElement) levelNumberElement.textContent = currentLevelIndex + 1;
    if (scoreNumberElement) scoreNumberElement.textContent = `${score}/${getTotalQuestionsEncounteredInTopic()}`;
    if (questionElement) questionElement.textContent = qData.question;

    if (lifelineFiftyFiftyButton) {
        lifelineFiftyFiftyButton.disabled = fiftyFiftyLifelinesUsedThisLevel || qData.options.length <= 2;
    }

    if (optionsElement) { // Options already cleared, now populate
        qData.options.forEach(opt => {
            const btn = document.createElement('button');
            btn.textContent = opt;
            btn.classList.add('option-button');
            btn.addEventListener('click', () => selectAnswer(btn, opt, qData.answer));
            optionsElement.appendChild(btn);
        });
    }
    updateLevelProgressVisuals();
    startTimer();
    adjustMainContentPadding();
}
// ====================================================================================
// MODIFIED loadQuestion FUNCTION ENDS HERE
// ====================================================================================

function selectAnswer(selectedButton, selectedOption, correctAnswer) {
    clearInterval(timerInterval);
    if (lifelineFiftyFiftyButton) lifelineFiftyFiftyButton.disabled = true;

    // Disable all option buttons after an answer is selected
    if (optionsElement) {
        Array.from(optionsElement.children).forEach(b => { b.disabled = true; });
    }

    const levelObj = triviaData[currentTopic]?.[currentLevelIndex];
    const questionData = levelObj?.questions?.[currentQuestionIndex]; // For source link

    if (!questionData) { // Should ideally not happen if loadQuestion worked
        if (feedbackElement) feedbackElement.textContent = "Error: Could not verify answer details.";
        if (feedbackFooter) feedbackFooter.classList.remove('hidden');
        if (mainNextButton) {
            mainNextButton.classList.remove('hidden');
             // Attempt to focus even in error case if button is shown
            setTimeout(() => {
                if (mainNextButton && typeof mainNextButton.focus === 'function') {
                    try { mainNextButton.focus(); } catch (e) { console.warn("Error focusing Next Q btn (err path):", e); }
                }
            }, 50);
        }
        return;
    }

    let feedbackMsg = '';
    if (selectedOption === correctAnswer) {
        selectedButton.classList.add('correct-answer');
        if (feedbackElement) feedbackElement.textContent = 'Correct!';
        if (feedbackElement) feedbackElement.classList.add('correct'); // For potential specific styling via .feedback-text.correct
        if (feedbackFooter) {
            feedbackFooter.classList.add('correct-state');
            feedbackFooter.classList.remove('incorrect-state');
        }
        score++;
        correctAnswersInLevel++;
    } else {
        selectedButton.classList.add('incorrect-answer');
        feedbackMsg = `Incorrect. The correct answer was: <strong>${correctAnswer}</strong>.`;
        if (questionData.sourceUrl && questionData.sourceTitle) {
            feedbackMsg += ` <a href="${questionData.sourceUrl}" target="_blank" class="source-link">Learn more: ${questionData.sourceTitle}</a>`;
        }
        if (feedbackElement) feedbackElement.innerHTML = feedbackMsg;
        if (feedbackElement) feedbackElement.classList.add('incorrect'); // For potential specific styling via .feedback-text.incorrect
        if (feedbackFooter) {
            feedbackFooter.classList.add('incorrect-state');
            feedbackFooter.classList.remove('correct-state');
        }

        // Highlight the actual correct answer if one was missed
        const correctButton = Array.from(optionsElement?.children || []).find(b => b.textContent === correctAnswer);
        if (correctButton) correctButton.classList.add('correct-answer');
    }

    if (scoreNumberElement) scoreNumberElement.textContent = `${score}/${getTotalQuestionsEncounteredInTopic() + 1}`; // +1 as this question is now answered
    if (feedbackFooter) feedbackFooter.classList.remove('hidden');

    if (mainNextButton) {
        mainNextButton.classList.remove('hidden');
        // SET FOCUS TO THE NEXT QUESTION BUTTON
        setTimeout(() => { // Use a small timeout to ensure it's focusable
            if (mainNextButton && typeof mainNextButton.focus === 'function') {
                try {
                    mainNextButton.focus();
                } catch (e) {
                    console.warn("Error focusing Next Question button in selectAnswer:", e);
                }
            }
        }, 50);
    }
    saveGameState();
}

function nextQuestion() {
    currentQuestionIndex++;
    loadQuestion(); // loadQuestion will now correctly handle if it's end of level or actual next question
}

function proceedToNextLevelFlow(wasLevelSkipped = false) {
    if (wasLevelSkipped) {
        currentLevelIndex++; currentQuestionIndex = 0; correctAnswersInLevel = 0; fiftyFiftyLifelinesUsedThisLevel = false;
        loadQuestion(); // Attempt to load next level; loadQuestion handles if it's end of game or another empty level
        return;
    }

    // This part is for when a non-empty level's questions are finished
    const levels = triviaData[currentTopic]; // Assume currentTopic is valid
    const currentLvlObj = levels?.[currentLevelIndex];
    const questionsInLvl = currentLvlObj?.questions?.length || 0;
    const minCorrect = questionsInLvl > 0 ? Math.min(3, questionsInLvl) : 0;

    if (correctAnswersInLevel >= minCorrect) { // Passed the level
        showMessageBoxContent('levelComplete', `You've conquered Level ${currentLevelIndex + 1}!`, 'Level Cleared!');
        // "Continue" from this modal will call proceedToNextLevel()
    } else { // Failed the level
        showMessageBoxContent('retryLevel',
            `You answered ${correctAnswersInLevel} of ${questionsInLvl} correctly. Need ${minCorrect} to proceed.`,
            `Level ${currentLevelIndex + 1} Challenge`
        );
    }
}

function handleRetryLevel() {
    hideMessageBox(); currentQuestionIndex = 0; correctAnswersInLevel = 0; fiftyFiftyLifelinesUsedThisLevel = false;
    loadQuestion(); saveGameState();
}

function proceedToNextLevel() { // Called from "Continue" on "Level Cleared" modal
    hideMessageBox();
    const levels = triviaData[currentTopic];
    if (currentLevelIndex < levels.length - 1) { // If there IS a next level
        currentLevelIndex++; currentQuestionIndex = 0; correctAnswersInLevel = 0; fiftyFiftyLifelinesUsedThisLevel = false;
        let articleDetails = null;
        const upcomingLvlObj = triviaData[currentTopic]?.[currentLevelIndex];
        if (upcomingLvlObj?.levelArticleUrl && upcomingLvlObj?.levelArticleTitle) {
            articleDetails = { url: upcomingLvlObj.levelArticleUrl, title: upcomingLvlObj.levelArticleTitle };
        }
        showMessageBoxContent('newLevel', `Get ready for Level ${currentLevelIndex + 1}.`, `Level ${currentLevelIndex + 1} Unlocked!`, articleDetails);
        // "Continue" from this modal will call continueAfterNewLevelPopup()
    } else { // This was the last level, and it was cleared.
        endGame();
    }
}

function continueAfterNewLevelPopup() { // Called from "Continue" on "New Level Unlocked" modal
    hideMessageBox();
    loadQuestion(); // NOW load the first question of the new level
    saveGameState();
}

function endGame() {
    clearInterval(timerInterval); if (lifelineFiftyFiftyButton) lifelineFiftyFiftyButton.disabled = true;
    // Feedback footer from last question should be hidden by loadQuestion if game ends there,
    // or by this function if called directly.
    if (feedbackFooter && !feedbackFooter.classList.contains('hidden')) {
        feedbackFooter.classList.add('hidden');
    }
    if (gameFooter) gameFooter.classList.remove('hidden');
    if (endGameArea) endGameArea.classList.remove('hidden');
    // Keep questionArea as is, gameFooter will overlay. Or hide it if preferred:
    // if (questionArea) questionArea.classList.add('hidden');

    const totalQs = totalQuestionsInTopic || 'all attempted';
    if (finalScoreDisplay) finalScoreDisplay.textContent = `Final score for "${currentTopic}": ${score}/${totalQs}.`;
    clearGameState();
}

function restartGame() {
    clearInterval(timerInterval); currentTopic = null; currentLevelIndex = 0; currentQuestionIndex = 0;
    score = 0; correctAnswersInLevel = 0; totalQuestionsInTopic = 0; fiftyFiftyLifelinesUsedThisLevel = false;
    if (levelNumberElement) levelNumberElement.textContent = '1'; if (scoreNumberElement) scoreNumberElement.textContent = '0/0';
    if (questionProgressDisplayElement) questionProgressDisplayElement.textContent = '0/0';
    if (levelProgressBarElement) levelProgressBarElement.style.width = '0%';
    if (questionArea && !questionArea.classList.contains('hidden')) questionArea.classList.add('hidden');
    if (gameFooter && !gameFooter.classList.contains('hidden')) gameFooter.classList.add('hidden'); // Hide endgame footer
    showPreloadScreen();
}

// --- Event Handlers Setup ---
function setupEventListeners() {
    if (startGameButton) {
        startGameButton.addEventListener('click', () => {
            if (!triviaData || Object.keys(triviaData).length === 0) {
                showMessageBoxContent('genericInfo', "Trivia data not loaded.", "Data Error"); return;
            }
            if (typeof triviaGameSettings !== 'undefined' && triviaGameSettings.gameLogoUrl && topicSelectionLogo) {
                topicSelectionLogo.src = triviaGameSettings.gameLogoUrl; topicSelectionLogo.alt = "Game Logo";
            }
            if (topicSelectionLogo) topicSelectionLogo.classList.remove('hidden');
            if (preloadGameTitle) preloadGameTitle.classList.add('hidden');
            if (welcomeParagraph) welcomeParagraph.classList.add('hidden');
            if (startGameButton) startGameButton.classList.add('hidden');
            if (chooseTopicHeading) chooseTopicHeading.classList.remove('hidden');
            if (topicOptionsPreloadContainer) {
                topicOptionsPreloadContainer.innerHTML = '';
                const topics = Object.keys(triviaData).sort();
                if (topics.length === 0) { if (chooseTopicHeading) chooseTopicHeading.textContent = "No topics."; }
                else { if (chooseTopicHeading) chooseTopicHeading.textContent = "Choose a Topic:"; }
                topics.forEach(name => {
                    const btn = document.createElement('button'); btn.textContent = name; btn.classList.add('option-button');
                    btn.addEventListener('click', () => selectTopic(name)); topicOptionsPreloadContainer.appendChild(btn);
                });
                const exploreBtn = document.createElement('a'); exploreBtn.textContent = "Explore DigitrendZ";
                exploreBtn.href = "https://digitrendz.blog/"; exploreBtn.target = "_blank";
                exploreBtn.classList.add('option-button', 'explore-digitrendz-button');
                topicOptionsPreloadContainer.appendChild(exploreBtn);
                topicOptionsPreloadContainer.classList.remove('hidden');
            }
        });
    }
    if (mainNextButton) mainNextButton.addEventListener('click', nextQuestion);
    if (restartButton) restartButton.addEventListener('click', restartGame);
    if (exitButton) exitButton.addEventListener('click', () => {
        if (confirm("Exit to Wiz Consults homepage? Progress NOT saved.")) window.location.href = 'https://www.wizconsults.com/';
    });
    if (homeButton) homeButton.addEventListener('click', () => {
        clearInterval(timerInterval);
        showMessageBoxContent('home', 'Go to main menu? Progress will be reset.');
    });
    if (lifelineFiftyFiftyButton) lifelineFiftyFiftyButton.addEventListener('click', useFiftyFiftyLifeline);
    if (closeLoadMessageButton) closeLoadMessageButton.addEventListener('click', hideMessageBox);
    if (saveExitButton) saveExitButton.addEventListener('click', () => {
        if (currentTopic) saveGameState(); clearInterval(timerInterval);
        showMessageBoxContent('genericInfo', "Progress saved. Safe to close.", "Saved");
    });
    if (exitWithoutSaveButton) exitWithoutSaveButton.addEventListener('click', () => {
        clearGameState(); clearInterval(timerInterval);
        showMessageBoxContent('genericInfo', "Progress not saved. Safe to close.", "Exited");
    });
    if (cancelExitButton) cancelExitButton.addEventListener('click', () => {
        hideMessageBox(); if (gameArea && !gameArea.classList.contains('hidden') && timeLeft > 0 && timeLeft < TIMER_DURATION) startTimer();
    });
    if (continueButton) continueButton.addEventListener('click', proceedToNextLevel);
    if (newLevelContinueButton) newLevelContinueButton.addEventListener('click', continueAfterNewLevelPopup);
    if (confirmHomeButton) confirmHomeButton.addEventListener('click', () => { hideMessageBox(); clearGameState(); restartGame(); });
    if (cancelHomeButton) cancelHomeButton.addEventListener('click', () => {
        hideMessageBox(); if (gameArea && !gameArea.classList.contains('hidden') && timeLeft > 0 && timeLeft < TIMER_DURATION) startTimer();
    });
    if (retryLevelButton) retryLevelButton.addEventListener('click', handleRetryLevel);
    if (retryMenuButton) retryMenuButton.addEventListener('click', () => { hideMessageBox(); clearGameState(); restartGame(); });
    if (genericInfoOkButton) genericInfoOkButton.addEventListener('click', hideMessageBox);
    window.addEventListener('resize', adjustMainContentPadding);
}

// --- Initial Load and Game Initialization ---
function initializeGameWithData(data) {
    if (typeof data !== 'object' || data === null || Object.keys(data).length === 0) {
        console.error("Trivia data is empty, null, or invalid.");
        if (preloadScreen && gameHeader) {
            preloadScreen.innerHTML = `<div class="preload-content-wrapper"><h1>Error Loading Game Data</h1><p>Data file empty/corrupted.</p></div>`;
            preloadScreen.classList.remove('hidden'); gameHeader.classList.add('hidden');
        } return;
    }
    triviaData = data; setupEventListeners(); loadGameState();
}

// --- Execution Start ---
document.addEventListener('DOMContentLoaded', () => {
    if (typeof triviaGameSettings === 'undefined' || !triviaGameSettings.jsonDataUrl) {
        console.error("Localization 'triviaGameSettings' or 'jsonDataUrl' not found.");
        if (preloadScreen && gameHeader) {
            preloadScreen.innerHTML = `<div class="preload-content-wrapper"><h1>Config Error</h1><p>Cannot load questions.</p></div>`;
            preloadScreen.classList.remove('hidden'); gameHeader.classList.add('hidden');
        } return;
    }
    fetch(triviaGameSettings.jsonDataUrl)
        .then(response => { if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`); return response.json(); })
        .then(data => initializeGameWithData(data))
        .catch(error => {
            console.error("Error loading trivia data via fetch:", error);
            if (preloadScreen && gameHeader) {
                preloadScreen.innerHTML = `<div class="preload-content-wrapper"><h1>Error Loading Data</h1><p>Could not fetch questions. ${error.message}</p></div>`;
                preloadScreen.classList.remove('hidden'); gameHeader.classList.add('hidden');
            }
        });
});