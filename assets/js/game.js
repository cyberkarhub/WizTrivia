// --- game.js - With Targeted Fix for UI Stability During Modals ---

// --- Global Variables ---
let triviaData = {};
let currentTopic = null;
let currentLevelIndex = 0;
let currentQuestionIndex = 0;
let score = 0;
let totalQuestionsInTopic = 0; // To be calculated when topic is selected
let correctAnswersInLevel = 0; // For level progression logic
const TIMER_DURATION = 20; // seconds
let timeLeft = TIMER_DURATION;
let timerInterval = null;
let fiftyFiftyLifelinesUsedThisLevel = false;
const MIN_CORRECT_TO_PASS_LEVEL = 3; // Example: User needs 3 correct to pass a level of 5

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
const preloadGameTitle = preloadScreen?.querySelector('.game-title'); // The H1 title on preload
const welcomeParagraph = document.getElementById('welcome-paragraph');
const chooseTopicHeading = document.getElementById('choose-topic-heading');
const topicOptionsPreloadContainer = document.getElementById('topic-options-preload');

const allModalContentPanes = [
    loadMessageContent, exitMessageContent, levelCompleteMessageContent,
    homeMessageContent, newLevelMessageContent, retryLevelMessageContent, genericInfoContent
];

// --- Utility Functions ---
function adjustMainContentPadding() {
    // Implementation can be added if dynamic padding based on footer visibility is needed.
}

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

function calculateTotalQuestionsInTopic(topicKey) {
    let total = 0;
    if (!triviaData || !topicKey || !triviaData[topicKey] || !Array.isArray(triviaData[topicKey])) return 0;
    const topicLevels = triviaData[topicKey];
    topicLevels.forEach(level => {
        if (level?.questions?.length) {
            total += level.questions.length;
        }
    });
    return total;
}

// --- Game State Management ---
function resetGameState() {
    currentTopic = null;
    currentLevelIndex = 0;
    currentQuestionIndex = 0;
    score = 0;
    totalQuestionsInTopic = 0;
    correctAnswersInLevel = 0;
    timeLeft = TIMER_DURATION;
    clearInterval(timerInterval);
    fiftyFiftyLifelinesUsedThisLevel = false;
}

function saveGameState() {
    if (!currentTopic) return;
    const gameState = {
        currentTopic,
        currentLevelIndex,
        currentQuestionIndex,
        score,
        correctAnswersInLevel,
        timeLeft,
        fiftyFiftyLifelinesUsedThisLevel
    };
    localStorage.setItem('triviaGameState', JSON.stringify(gameState));
}

function loadGameState() {
    const savedState = localStorage.getItem('triviaGameState');
    if (savedState) {
        try {
            const gameState = JSON.parse(savedState);
            if (gameState.currentTopic && typeof gameState.currentLevelIndex === 'number' &&
                triviaData && triviaData[gameState.currentTopic] &&
                triviaData[gameState.currentTopic][gameState.currentLevelIndex]) {

                currentTopic = gameState.currentTopic;
                currentLevelIndex = gameState.currentLevelIndex;
                currentQuestionIndex = gameState.currentQuestionIndex || 0;
                score = gameState.score || 0;
                correctAnswersInLevel = gameState.correctAnswersInLevel || 0;
                timeLeft = typeof gameState.timeLeft === 'number' ? gameState.timeLeft : TIMER_DURATION;
                fiftyFiftyLifelinesUsedThisLevel = gameState.fiftyFiftyLifelinesUsedThisLevel || false;
                totalQuestionsInTopic = calculateTotalQuestionsInTopic(currentTopic);
                return true;
            }
        } catch (e) {
            console.error("Error parsing saved game state:", e);
            localStorage.removeItem('triviaGameState');
        }
    }
    return false;
}

function clearGameState() {
    localStorage.removeItem('triviaGameState');
}

// --- Core Game Logic ---
function loadQuestion(iteration = 0) {
    if (iteration > 10) {
        showMessageBoxContent('genericInfo', "No valid questions available. Please try starting a new game or topic.", "Data Error");
        return;
    }
    clearInterval(timerInterval);

    if (questionArea && questionArea.classList.contains('hidden')) {
        questionArea.classList.remove('hidden');
    }
    if (gameArea && gameArea.classList.contains('hidden')) {
         if (preloadScreen) preloadScreen.classList.add('hidden');
         if (gameHeader) gameHeader.classList.remove('hidden');
         gameArea.classList.remove('hidden');
    }

    if (feedbackElement) { feedbackElement.innerHTML = ''; feedbackElement.className = 'feedback-text'; }
    if (mainNextButton) mainNextButton.classList.add('hidden');
    if (gameFooter) gameFooter.classList.add('hidden');
    if (feedbackFooter) {
        feedbackFooter.classList.add('hidden');
        feedbackFooter.classList.remove('correct-state', 'incorrect-state');
    }

    if (!currentTopic || !triviaData[currentTopic]) {
        console.error("loadQuestion: currentTopic or triviaData for topic is missing.");
        restartGame(); return;
    }
    const levels = triviaData[currentTopic];

    if (currentLevelIndex >= levels.length) {
        endGame();
        return;
    }

    const levelObj = levels[currentLevelIndex];

    if (!levelObj || !levelObj.questions || !Array.isArray(levelObj.questions) || levelObj.questions.length === 0) {
        console.warn(`Level ${currentLevelIndex + 1} for topic ${currentTopic} has no questions or is malformed. Attempting to skip.`);
        proceedToNextLevelFlow(true);
        return;
    }

    if (currentQuestionIndex >= levelObj.questions.length) {
        proceedToNextLevelFlow(false);
        return;
    }

    if (optionsElement) optionsElement.innerHTML = '';
    if (questionElement) questionElement.textContent = '';

    const qData = levelObj.questions[currentQuestionIndex];
    if (!qData || !qData.question || !qData.options || qData.options.length < 2 || !qData.answer) {
        console.warn(`Question ${currentQuestionIndex + 1} in Level ${currentLevelIndex + 1} for topic ${currentTopic} is invalid. Skipping.`);
        currentQuestionIndex++;
        loadQuestion(iteration + 1);
        return;
    }

    if (levelNumberElement) levelNumberElement.textContent = currentLevelIndex + 1;
    if (scoreNumberElement) scoreNumberElement.textContent = `${score}/${getTotalQuestionsEncounteredInTopic()}`;
    if (questionElement) questionElement.textContent = qData.question;

    if (lifelineFiftyFiftyButton) {
        lifelineFiftyFiftyButton.disabled = fiftyFiftyLifelinesUsedThisLevel || qData.options.length <= 2;
    }

    if (optionsElement) {
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
    saveGameState();
}

function selectAnswer(button, selectedOption, correctAnswer) {
    clearInterval(timerInterval);
    disableOptions();

    const isCorrect = selectedOption === correctAnswer;
    if (isCorrect) {
        score++;
        correctAnswersInLevel++;
        button.classList.add('correct-answer');
        showFeedback('Correct!', 'correct-state', correctAnswer);
    } else {
        button.classList.add('incorrect-answer');
        const allOptionButtons = optionsElement.querySelectorAll('.option-button');
        allOptionButtons.forEach(optBtn => {
            if (optBtn.textContent === correctAnswer) {
                optBtn.classList.add('correct-answer');
            }
        });
        showFeedback(`Incorrect. The correct answer was: ${correctAnswer}`, 'incorrect-state', correctAnswer);
    }
    updateLevelProgressVisuals();
    saveGameState();
}

function showFeedback(message, feedbackClass, correctAnswer) {
    const currentQuestionData = triviaData[currentTopic][currentLevelIndex].questions[currentQuestionIndex];
    let sourceInfo = '';
    if (currentQuestionData.sourceUrl && currentQuestionData.sourceTitle) {
        sourceInfo = ` <a href="${currentQuestionData.sourceUrl}" target="_blank" class="source-link">(Source: ${currentQuestionData.sourceTitle})</a>`;
    } else if (currentQuestionData.sourceUrl) {
        sourceInfo = ` <a href="${currentQuestionData.sourceUrl}" target="_blank" class="source-link">(Source)</a>`;
    }

    if (feedbackElement) feedbackElement.innerHTML = `${message}${sourceInfo}`;
    if (feedbackFooter) {
        feedbackFooter.className = 'feedback-footer'; // Reset classes
        feedbackFooter.classList.add(feedbackClass);
        feedbackFooter.classList.remove('hidden');
    }
    if (mainNextButton) mainNextButton.classList.remove('hidden');
    adjustMainContentPadding();
}

function nextQuestion() {
    currentQuestionIndex++;
    loadQuestion();
}

function startTimer() {
    timeLeft = TIMER_DURATION;
    updateTimerDisplay();
    timerInterval = setInterval(() => {
        timeLeft--;
        updateTimerDisplay();
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            timeUp();
        }
    }, 1000);
}

function updateTimerDisplay() {
    if (!timerDisplayElement) return;
    const minutes = Math.floor(timeLeft / 60);
    const seconds = timeLeft % 60;
    timerDisplayElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
}

function stopTimer() {
    clearInterval(timerInterval);
}

function timeUp() {
    disableOptions();
    const correctAnswer = triviaData[currentTopic][currentLevelIndex].questions[currentQuestionIndex].answer;
    const allOptionButtons = optionsElement.querySelectorAll('.option-button');
    allOptionButtons.forEach(optBtn => {
        if (optBtn.textContent === correctAnswer) {
            optBtn.classList.add('correct-answer');
        }
    });
    showFeedback(`Time's up! The correct answer was: ${correctAnswer}`, 'incorrect-state', correctAnswer);
    updateLevelProgressVisuals();
    saveGameState();
}

function disableOptions() {
    const optionButtons = optionsElement.querySelectorAll('.option-button');
    optionButtons.forEach(button => button.disabled = true);
}

function useFiftyFiftyLifeline() {
    if (fiftyFiftyLifelinesUsedThisLevel || !lifelineFiftyFiftyButton) return;

    const currentQuestionData = triviaData[currentTopic][currentLevelIndex].questions[currentQuestionIndex];
    const correctAnswer = currentQuestionData.answer;
    const incorrectOptions = currentQuestionData.options.filter(opt => opt !== correctAnswer);

    if (incorrectOptions.length < 2 || currentQuestionData.options.length <=2) {
        lifelineFiftyFiftyButton.disabled = true;
        return;
    }

    for (let i = incorrectOptions.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [incorrectOptions[i], incorrectOptions[j]] = [incorrectOptions[j], incorrectOptions[i]];
    }

    const optionsToHide = incorrectOptions.slice(0, Math.min(2, incorrectOptions.length -1));

    let hiddenCount = 0;
    const allOptionButtons = optionsElement.querySelectorAll('.option-button');
    allOptionButtons.forEach(button => {
        if (optionsToHide.includes(button.textContent) && button.textContent !== correctAnswer && hiddenCount < 2) {
            button.classList.add('hidden-by-lifeline');
            button.disabled = true;
            hiddenCount++;
        }
    });

    fiftyFiftyLifelinesUsedThisLevel = true;
    lifelineFiftyFiftyButton.disabled = true;
    saveGameState();
}

function updateLevelProgressVisuals() {
    const levelObj = triviaData[currentTopic]?.[currentLevelIndex];
    if (!levelObj || !levelObj.questions || levelObj.questions.length === 0) {
        if(questionProgressDisplayElement) questionProgressDisplayElement.textContent = `0/0`;
        if(levelProgressBarElement) levelProgressBarElement.style.width = `0%`;
        return;
    }

    const totalQuestionsInLevel = levelObj.questions.length;
    let questionsAnsweredInLevel = currentQuestionIndex;
    if (feedbackFooter && !feedbackFooter.classList.contains('hidden')) { // If feedback is shown, current question is considered answered for progress
        questionsAnsweredInLevel = currentQuestionIndex + 1;
    }


    if (questionProgressDisplayElement) {
        let currentQNumDisplay = currentQuestionIndex + 1;
        if (currentQNumDisplay > totalQuestionsInLevel && totalQuestionsInLevel > 0) {
             currentQNumDisplay = totalQuestionsInLevel;
        }
        questionProgressDisplayElement.textContent = `${Math.min(currentQNumDisplay, totalQuestionsInLevel)}/${totalQuestionsInLevel}`;
    }
    if (levelProgressBarElement) {
        const progressPercentage = totalQuestionsInLevel > 0 ? (Math.min(questionsAnsweredInLevel, totalQuestionsInLevel) / totalQuestionsInLevel) * 100 : 0;
        levelProgressBarElement.style.width = `${progressPercentage}%`;
    }
}

// --- Modal Handling ---
function hideAllModalPanes() {
    allModalContentPanes.forEach(pane => {
        if (pane) pane.classList.remove('visible');
    });
}

function showMessageBoxContent(type, message, title = '') {
    hideAllModalPanes();
    if (!messageBox) return;
    messageBox.classList.remove('hidden');
    adjustMainContentPadding();

    switch (type) {
        case 'load':
            if (loadMessageContent && loadMessageText) {
                loadMessageText.textContent = message;
                loadMessageContent.classList.add('visible');
            }
            break;
        case 'exit':
            if (exitMessageContent && exitMessageText) {
                exitMessageText.textContent = message;
                exitMessageContent.classList.add('visible');
            }
            break;
        case 'levelComplete':
            if (levelCompleteMessageContent && levelCompleteTitle && levelCompleteText) {
                levelCompleteTitle.textContent = title || "Level Complete!";
                levelCompleteText.textContent = message;
                levelCompleteMessageContent.classList.add('visible');
            }
            break;
        case 'home':
            if (homeMessageContent && homeMessageText) {
                homeMessageText.textContent = message;
                homeMessageContent.classList.add('visible');
            }
            break;
        case 'newLevel':
            if (newLevelMessageContent && newLevelTitleElement && newLevelMainText && newLevelArticleSuggestion && newLevelArticleLink) {
                newLevelTitleElement.textContent = title || "New Level Unlocked!";
                newLevelMainText.textContent = message;
                const levelData = triviaData[currentTopic]?.[currentLevelIndex];
                if (levelData?.levelArticleUrl && levelData?.levelArticleTitle) {
                    newLevelArticleSuggestion.textContent = `Want a hint for the next level? Check out:`;
                    newLevelArticleLink.href = levelData.levelArticleUrl;
                    newLevelArticleLink.textContent = levelData.levelArticleTitle;
                    newLevelArticleLink.classList.remove('hidden');
                } else {
                    newLevelArticleSuggestion.textContent = '';
                    newLevelArticleLink.classList.add('hidden');
                }
                newLevelMessageContent.classList.add('visible');
            }
            break;
        case 'retryLevel':
             if (retryLevelMessageContent && retryLevelTitleElement && retryLevelText) {
                retryLevelTitleElement.textContent = title || "Not Quite There!";
                retryLevelText.textContent = message;
                retryLevelMessageContent.classList.add('visible');
            }
            break;
        case 'genericInfo':
            if (genericInfoContent && genericInfoText) {
                genericInfoText.textContent = message;
                genericInfoContent.classList.add('visible');
            }
            break;
    }
}

function closeModal() {
    if (messageBox) messageBox.classList.add('hidden');
    hideAllModalPanes();
    adjustMainContentPadding();
}

// --- Navigation & Game Flow ---
function proceedToNextLevelFlow(wasLevelSkippedDueToError = false) {
    clearInterval(timerInterval);

    if (wasLevelSkippedDueToError) {
        currentLevelIndex++;
        currentQuestionIndex = 0;
        correctAnswersInLevel = 0;
        fiftyFiftyLifelinesUsedThisLevel = false;
        if (currentLevelIndex < triviaData[currentTopic].length) {
            showMessageBoxContent('newLevel', `Moving to the next challenge as the previous one had no questions.`, `Level ${currentLevelIndex + 1}`);
        } else {
            endGame();
        }
        return;
    }

    if (correctAnswersInLevel >= MIN_CORRECT_TO_PASS_LEVEL || triviaData[currentTopic][currentLevelIndex].questions.length < MIN_CORRECT_TO_PASS_LEVEL) {
        currentLevelIndex++;
        currentQuestionIndex = 0;
        correctAnswersInLevel = 0;
        fiftyFiftyLifelinesUsedThisLevel = false;
        if (currentLevelIndex < triviaData[currentTopic].length) {
            showMessageBoxContent('newLevel', `You've passed Level ${currentLevelIndex}! Get ready for Level ${currentLevelIndex + 1}.`, `Level ${currentLevelIndex + 1} Unlocked!`);
        } else {
            endGame();
        }
    } else {
        const message = `You needed ${MIN_CORRECT_TO_PASS_LEVEL} correct answers to pass Level ${currentLevelIndex + 1}, but got ${correctAnswersInLevel}. Would you like to retry or go to the main menu?`;
        showMessageBoxContent('retryLevel', message, `Level ${currentLevelIndex + 1} - Try Again?`);
    }
    saveGameState();
}

function handleLevelCompletion() {
    closeModal();
    loadQuestion();
}

function handleLevelRetry() {
    closeModal();
    currentQuestionIndex = 0;
    correctAnswersInLevel = 0;
    fiftyFiftyLifelinesUsedThisLevel = false;
    loadQuestion();
}

function startGame(topicKey) {
    currentTopic = topicKey;
    resetGameState();
    currentTopic = topicKey;
    totalQuestionsInTopic = calculateTotalQuestionsInTopic(currentTopic);

    if (totalQuestionsInTopic === 0) {
        showMessageBoxContent('genericInfo', `The selected topic "${currentTopic}" currently has no questions. Please select another topic or try again later.`, "No Questions");
        startTopicSelection(); // Go back to topic selection (which will now show welcome elements by default)
        return;
    }

    if (preloadScreen) preloadScreen.classList.add('hidden');
    if (gameHeader) gameHeader.classList.remove('hidden');
    if (gameArea) gameArea.classList.remove('hidden');
    if (gameFooter) gameFooter.classList.add('hidden');
    if (feedbackFooter) feedbackFooter.classList.add('hidden');

    fiftyFiftyLifelinesUsedThisLevel = false;
    if(lifelineFiftyFiftyButton) lifelineFiftyFiftyButton.disabled = false;

    loadQuestion();
}

function endGame() {
    clearInterval(timerInterval);
    if (gameArea) gameArea.classList.add('hidden');
    if (feedbackFooter) feedbackFooter.classList.add('hidden');
    if (gameHeader) gameHeader.classList.add('hidden'); // Hide game header at end screen
    if (gameFooter) gameFooter.classList.remove('hidden');
    if (finalScoreDisplay) finalScoreDisplay.textContent = `Your final score is ${score} out of ${totalQuestionsInTopic}.`;
    if (preloadScreen) preloadScreen.classList.remove('hidden'); // Show preload screen as background/container for end game
        // Hide welcome/topic specific elements within preloadScreen
        if (preloadGameTitle) preloadGameTitle.classList.add('hidden');
        if (welcomeParagraph) welcomeParagraph.classList.add('hidden');
        if (startGameButton) startGameButton.classList.add('hidden');
        if (chooseTopicHeading) chooseTopicHeading.classList.add('hidden');
        if (topicOptionsPreloadContainer) topicOptionsPreloadContainer.classList.add('hidden');
        if (topicSelectionLogo) topicSelectionLogo.classList.remove('hidden'); // Show logo on end screen

    adjustMainContentPadding();
    clearGameState();
}

function restartGame() {
    resetGameState();
    clearGameState();
    // Instead of directly calling startTopicSelection, go through the initial flow logic
    // This ensures DOMContentLoaded -> fetchTriviaData -> (no saved state) -> show welcome
    // Forcing a "fresh start" feel.
    // Hide game elements, show preload shell. fetchTriviaData will handle the rest.
    if (gameArea) gameArea.classList.add('hidden');
    if (gameHeader) gameHeader.classList.add('hidden');
    if (gameFooter) gameFooter.classList.add('hidden');
    if (feedbackFooter) feedbackFooter.classList.add('hidden');
    if (preloadScreen) preloadScreen.classList.remove('hidden');
    // Let fetchTriviaData re-evaluate and show the welcome screen as no game state will be found.
    fetchTriviaData();
}

function goHome() {
    closeModal();
    restartGame();
}

function exitGameConfirmation() {
    stopTimer();
    showMessageBoxContent('exit', 'Are you sure you want to exit? Your current progress for this topic will be saved.');
}

function handleHomeConfirmation() {
    stopTimer();
    showMessageBoxContent('home', 'Are you sure you want to return to the main menu? Your current session progress will be saved.');
}

// --- Initialization and Topic Selection ---
function startTopicSelection() {
    resetGameState(); // Full reset before topic selection
    clearGameState(); // Clear any residual saved state

    if (preloadScreen) preloadScreen.classList.remove('hidden');
    if (gameHeader) gameHeader.classList.add('hidden');
    if (gameArea) gameArea.classList.add('hidden');
    if (gameFooter) gameFooter.classList.add('hidden');
    if (feedbackFooter) feedbackFooter.classList.add('hidden');

    if (topicSelectionLogo && triviaGameSettings.gameLogoUrl) {
        topicSelectionLogo.src = triviaGameSettings.gameLogoUrl;
        topicSelectionLogo.classList.remove('hidden');
    } else if (topicSelectionLogo) {
        topicSelectionLogo.classList.add('hidden');
    }

    // Hide initial welcome elements, show topic selection ones
    if (preloadGameTitle) preloadGameTitle.classList.add('hidden'); // This is the H1
    if (welcomeParagraph) welcomeParagraph.classList.add('hidden');
    if (startGameButton) startGameButton.classList.add('hidden');

    if (chooseTopicHeading) chooseTopicHeading.classList.remove('hidden');
    if (topicOptionsPreloadContainer) {
        topicOptionsPreloadContainer.classList.remove('hidden');
        displayTopics();
    }
    adjustMainContentPadding();
}

function displayTopics() {
    if (!topicOptionsPreloadContainer) return;
    topicOptionsPreloadContainer.innerHTML = '';

    const topics = Object.keys(triviaData);
    if (topics.length === 0) {
        topicOptionsPreloadContainer.innerHTML = '<p>No trivia topics available at the moment. Please check back later.</p>';
        return;
    }

    topics.forEach(topic => {
        const topicBtn = document.createElement('button');
        topicBtn.textContent = topic;
        topicBtn.classList.add('option-button');
        topicBtn.addEventListener('click', () => startGame(topic));
        topicOptionsPreloadContainer.appendChild(topicBtn);
    });

    const exploreBtn = document.createElement('a');
    exploreBtn.href = "https://digitrendz.blog";
    exploreBtn.target = "_blank";
    exploreBtn.textContent = "Explore DigitrendZ Blog";
    exploreBtn.classList.add('option-button', 'explore-digitrendz-button');
    topicOptionsPreloadContainer.appendChild(exploreBtn);
}

async function fetchTriviaData() {
    try {
        const response = await fetch(triviaGameSettings.jsonDataUrl);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        triviaData = await response.json();
        if (Object.keys(triviaData).length === 0) {
             showMessageBoxContent('load', 'No trivia data found. The game cannot start.', 'Error');
             // Ensure welcome elements are explicitly hidden if game can't start
             if (preloadGameTitle) preloadGameTitle.classList.add('hidden');
             if (welcomeParagraph) welcomeParagraph.classList.add('hidden');
             if (startGameButton) startGameButton.classList.add('hidden');
             if (topicSelectionLogo) topicSelectionLogo.classList.add('hidden');
        } else {
            if (loadGameState()) { // Attempt to load game state
                totalQuestionsInTopic = calculateTotalQuestionsInTopic(currentTopic);
                if (preloadScreen) preloadScreen.classList.add('hidden'); // Hide preload screen if resuming game
                if (gameHeader) gameHeader.classList.remove('hidden');
                if (gameArea) gameArea.classList.remove('hidden');
                // Explicitly hide welcome elements that might have been shown by DOMContentLoaded
                if (topicSelectionLogo) topicSelectionLogo.classList.add('hidden');
                if (preloadGameTitle) preloadGameTitle.classList.add('hidden');
                if (welcomeParagraph) welcomeParagraph.classList.add('hidden');
                if (startGameButton) startGameButton.classList.add('hidden');
                loadQuestion(); // Resume game
            } else {
                // NO SAVED GAME STATE or invalid state: Show Welcome Screen elements
                if (preloadScreen) preloadScreen.classList.remove('hidden'); // Ensure container is visible
                if (gameHeader) gameHeader.classList.add('hidden');    // Game header remains hidden
                if (gameArea) gameArea.classList.add('hidden');        // Game area remains hidden

                // Explicitly show welcome elements
                if (topicSelectionLogo && triviaGameSettings.gameLogoUrl) {
                    topicSelectionLogo.src = triviaGameSettings.gameLogoUrl;
                    topicSelectionLogo.classList.remove('hidden');
                } else if (topicSelectionLogo) {
                    topicSelectionLogo.classList.add('hidden');
                }
                if (preloadGameTitle) preloadGameTitle.classList.remove('hidden');
                if (welcomeParagraph) welcomeParagraph.classList.remove('hidden');
                if (startGameButton) startGameButton.classList.remove('hidden');

                // Keep topic selection parts hidden until "Start Game" is clicked
                if (chooseTopicHeading) chooseTopicHeading.classList.add('hidden');
                if (topicOptionsPreloadContainer) topicOptionsPreloadContainer.classList.add('hidden');
            }
        }
    } catch (error) {
        console.error('Failed to load trivia data:', error);
        showMessageBoxContent('load', `Failed to load trivia data: ${error.message}. Please try refreshing the page.`, 'Error Loading Game');
        // Hide all interactive elements on critical load error
        if (preloadGameTitle) preloadGameTitle.classList.add('hidden');
        if (welcomeParagraph) welcomeParagraph.classList.add('hidden');
        if (startGameButton) startGameButton.classList.add('hidden');
        if (topicSelectionLogo) topicSelectionLogo.classList.add('hidden');
    }
}

function setupEventListeners() {
    if (startGameButton) {
        startGameButton.addEventListener('click', startTopicSelection);
    }
    if (mainNextButton) {
        mainNextButton.addEventListener('click', nextQuestion);
    }
    if (restartButton) {
        restartButton.addEventListener('click', restartGame);
    }
    if (lifelineFiftyFiftyButton) {
        lifelineFiftyFiftyButton.addEventListener('click', useFiftyFiftyLifeline);
    }
    if (exitButton) {
        exitButton.addEventListener('click', exitGameConfirmation);
    }
    if (homeButton) {
        homeButton.addEventListener('click', handleHomeConfirmation);
    }

    if (closeLoadMessageButton) closeLoadMessageButton.addEventListener('click', closeModal);
    if (saveExitButton) saveExitButton.addEventListener('click', () => { saveGameState(); goHome(); });
    if (exitWithoutSaveButton) exitWithoutSaveButton.addEventListener('click', () => { clearGameState(); goHome(); });
    if (cancelExitButton) cancelExitButton.addEventListener('click', () => { closeModal(); if(currentTopic) startTimer(); });
    if (continueButton) continueButton.addEventListener('click', handleLevelCompletion);
    if (confirmHomeButton) confirmHomeButton.addEventListener('click', goHome);
    if (cancelHomeButton) cancelHomeButton.addEventListener('click', () => { closeModal(); if(currentTopic) startTimer(); });
    if (newLevelContinueButton) newLevelContinueButton.addEventListener('click', handleLevelCompletion);
    if (retryLevelButton) retryLevelButton.addEventListener('click', handleLevelRetry);
    if (retryMenuButton) retryMenuButton.addEventListener('click', goHome);
    if (genericInfoOkButton) genericInfoOkButton.addEventListener('click', closeModal);
}

document.addEventListener('DOMContentLoaded', () => {
    if (typeof triviaGameSettings === 'undefined' || !triviaGameSettings.jsonDataUrl) {
        console.error('triviaGameSettings or jsonDataUrl is not defined. Cannot load game.');
        const body = document.querySelector('body');
        if (body) {
            body.innerHTML = '<p style="color: red; text-align: center; padding: 20px;">Error: Game configuration is missing. Please contact support.</p>';
        }
        return;
    }

    // Initial UI state setup
    if (preloadScreen) preloadScreen.classList.remove('hidden'); // Main container for welcome/topic
    if (gameArea) gameArea.classList.add('hidden');
    if (gameHeader) gameHeader.classList.add('hidden');
    if (gameFooter) gameFooter.classList.add('hidden');
    if (feedbackFooter) feedbackFooter.classList.add('hidden');

    // Initially hide all specific content within preloadScreen. fetchTriviaData will decide what to show.
    if (topicSelectionLogo) topicSelectionLogo.classList.add('hidden');
    if (preloadGameTitle) preloadGameTitle.classList.add('hidden');
    if (welcomeParagraph) welcomeParagraph.classList.add('hidden');
    if (startGameButton) startGameButton.classList.add('hidden');
    if (chooseTopicHeading) chooseTopicHeading.classList.add('hidden');
    if (topicOptionsPreloadContainer) topicOptionsPreloadContainer.classList.add('hidden');

    fetchTriviaData();
    setupEventListeners();
    adjustMainContentPadding();
});