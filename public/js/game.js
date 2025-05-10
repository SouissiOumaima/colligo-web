/**
 * Game script for the Educational Game Platform
 * Handles game functionality, timers, and interactions
 */

document.addEventListener('DOMContentLoaded', () => {
    // DOM elements
    const levelDisplay = document.getElementById('current-level');
    const stageDisplay = document.getElementById('current-stage');
    const totalStagesDisplay = document.getElementById('total-stages');
    const scoreDisplay = document.getElementById('current-score');
    const questionText = document.getElementById('question-text');
    const timerProgress = document.getElementById('timer-progress');
    const stageProgress = document.getElementById('stage-progress');
    const gameGrid = document.getElementById('game-grid');
    const feedbackContainer = document.getElementById('feedback-container');
    const resultModal = document.getElementById('result-modal');
    const resultTitle = document.getElementById('result-title');
    const resultMessage = document.getElementById('result-message');
    const resultAnimation = document.getElementById('result-animation');
    const continueBtn = document.getElementById('continue-btn');
    const homeBtn = document.getElementById('home-btn');
    const gameCharacter = document.getElementById('game-character');
    const characterSpeech = document.getElementById('character-speech');
    
    // Game configuration and state
    const config = {
        timeLimit: 10000, // 10 seconds in milliseconds
        totalStages: 3, // Stages per level
        maxLevel: 3, // Maximum game level
    };
    
    let gameState = {
        level: 1,
        stage: 1,
        score: 0,
        currentWord: '',
        correctImage: null,
        timerRunning: false,
        timerStartTime: 0,
        timerEndTime: 0,
        showingFeedback: false
    };
    
    // Character speech messages
    const characterMessages = {
        start: [
            'اختر الصورة الصحيحة!',
            'ركز جيدًا على السؤال!',
            'أنا أؤمن بك، يمكنك أن تفعلها!'
        ],
        correct: [
            'أحسنت! إجابة صحيحة!',
            'رائع! أنت تتعلم بسرعة!',
            'ممتاز! استمر على هذا المنوال!'
        ],
        wrong: [
            'لا بأس، حاول مرة أخرى!',
            'خطأ بسيط، أعتقد أنك تعرف الإجابة الصحيحة!',
            'لا تقلق، التعلم يأتي من المحاولة!'
        ]
    };
    
    // Game image data (simplified for demo)
    const gameData = {
        words: [
            { word: 'قط', imageUrl: 'images/cat.jpg' },
            { word: 'كلب', imageUrl: 'images/dog.jpg' },
            { word: 'أسد', imageUrl: 'images/lion.jpg' },
            { word: 'فيل', imageUrl: 'images/elephant.jpg' },
            { word: 'قرد', imageUrl: 'images/monkey.jpg' },
            { word: 'زرافة', imageUrl: 'images/giraffe.jpg' },
            { word: 'نمر', imageUrl: 'images/tiger.jpg' },
            { word: 'بقرة', imageUrl: 'images/cow.jpg' },
            { word: 'حصان', imageUrl: 'images/horse.jpg' },
            { word: 'دب', imageUrl: 'images/bear.jpg' }
        ]
    };
    
    // Initialize game
    initGame();
    
    /**
     * Initialize the game
     */
    function initGame() {
        // Get level from URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const levelParam = urlParams.get('level');
        
        if (levelParam && !isNaN(levelParam)) {
            gameState.level = Math.max(1, Math.min(parseInt(levelParam), config.maxLevel));
        }
        
        // Update UI displays
        updateDisplays();
        
        // Set up event listeners
        continueBtn.addEventListener('click', goToNextStage);
        homeBtn.addEventListener('click', () => window.location.href = 'index.html');
        
        // Start the first stage
        startStage();
        
        // Initialize character speech
        updateCharacterSpeech('start');
    }
    
    /**
     * Start a new game stage
     */
    function startStage() {
        // Reset state for new stage
        gameState.showingFeedback = false;
        gameState.timerRunning = false;
        
        // Hide feedback and result modal
        feedbackContainer.classList.remove('show');
        resultModal.classList.remove('show');
        
        // Update progress display
        stageProgress.style.width = `${(gameState.stage / config.totalStages) * 100}%`;
        
        // Create new question for the stage
        createQuestion();
        
        // Start the timer
        startTimer();
        
        // Update character
        gameCharacter.src = 'images/character-normal.svg';
        updateCharacterSpeech('start');
    }
    
    /**
     * Create a new question for the current stage
     */
    function createQuestion() {
        // Clear game grid
        gameGrid.innerHTML = '';
        
        // Select random images for the stage
        const shuffledWords = [...gameData.words].sort(() => Math.random() - 0.5);
        const stageWords = shuffledWords.slice(0, 4); // Get 4 random words
        
        // Select one as correct answer
        const correctIndex = Math.floor(Math.random() * stageWords.length);
        gameState.correctImage = stageWords[correctIndex];
        gameState.currentWord = gameState.correctImage.word;
        
        // Update question text
        questionText.textContent = `هل هذا "${gameState.currentWord}"؟`;
        
        // Create image cards
        stageWords.forEach((wordObj, index) => {
            const card = document.createElement('div');
            card.classList.add('game-card-item');
            card.dataset.word = wordObj.word;
            
            const img = document.createElement('img');
            img.src = wordObj.imageUrl;
            img.alt = wordObj.word;
            
            card.appendChild(img);
            gameGrid.appendChild(card);
            
            // Add click event listener
            card.addEventListener('click', () => checkAnswer(card));
        });
    }
    
    /**
     * Start the game timer
     */
    function startTimer() {
        // Reset timer
        timerProgress.style.width = '100%';
        timerProgress.style.backgroundColor = 'var(--success)';
        
        // Remove any existing animation
        timerProgress.classList.remove('running');
        
        // Start new timer
        setTimeout(() => {
            timerProgress.classList.add('running');
            gameState.timerRunning = true;
            gameState.timerStartTime = Date.now();
            gameState.timerEndTime = Date.now() + config.timeLimit;
            
            // Check time-out
            const timerId = setTimeout(() => {
                if (gameState.timerRunning) {
                    timeOut();
                }
            }, config.timeLimit);
            
            // Store timer ID to clear if needed
            gameState.timerId = timerId;
        }, 50);
    }
    
    /**
     * Handle time-out (when player doesn't answer in time)
     */
    function timeOut() {
        if (gameState.showingFeedback) return;
        
        gameState.timerRunning = false;
        gameState.showingFeedback = true;
        
        // Show incorrect answer feedback
        showFeedback(false);
        
        // Update character
        gameCharacter.src = 'images/character-sad.svg';
        updateCharacterSpeech('wrong');
    }
    
    /**
     * Check if the selected answer is correct
     * @param {HTMLElement} selectedCard - The card element the player clicked
     */
    function checkAnswer(selectedCard) {
        if (gameState.showingFeedback) return;
        
        gameState.timerRunning = false;
        gameState.showingFeedback = true;
        
        // Stop timer animation
        timerProgress.classList.remove('running');
        timerProgress.classList.add('paused');
        
        // Clear timeout
        clearTimeout(gameState.timerId);
        
        // Check if answer is correct
        const selectedWord = selectedCard.dataset.word;
        const isCorrect = selectedWord === gameState.currentWord;
        
        // Calculate points (more points for faster answers)
        let pointsEarned = 0;
        if (isCorrect) {
            const timeElapsed = Date.now() - gameState.timerStartTime;
            const timeRatio = 1 - (timeElapsed / config.timeLimit);
            pointsEarned = Math.max(1, Math.round(10 * timeRatio));
            
            // Add points to score
            gameState.score += pointsEarned;
            
            // Update score display
            scoreDisplay.textContent = gameState.score;
            
            // Add visual feedback to the selected card
            selectedCard.classList.add('correct-answer');
            
            // Update character
            gameCharacter.src = 'images/character-happy.svg';
            updateCharacterSpeech('correct');
        } else {
            // Add visual feedback to the selected card
            selectedCard.classList.add('wrong-answer');
            
            // Highlight the correct answer
            const cards = gameGrid.querySelectorAll('.game-card-item');
            cards.forEach(card => {
                if (card.dataset.word === gameState.currentWord) {
                    card.classList.add('correct-answer');
                }
            });
            
            // Update character
            gameCharacter.src = 'images/character-sad.svg';
            updateCharacterSpeech('wrong');
        }
        
        // Show feedback
        showFeedback(isCorrect, pointsEarned);
    }
    
    /**
     * Show feedback after answer
     * @param {boolean} isCorrect - Whether the answer was correct
     * @param {number} points - Points earned for correct answer
     */
    function showFeedback(isCorrect, points = 0) {
        // Create feedback in the container
        feedbackContainer.innerHTML = '';
        feedbackContainer.className = 'feedback-container';
        
        if (isCorrect) {
            feedbackContainer.classList.add('show', 'correct');
            
            const title = document.createElement('h3');
            title.classList.add('feedback-title');
            title.textContent = 'إجابة صحيحة!';
            
            const image = document.createElement('img');
            image.classList.add('feedback-image');
            image.src = 'images/correct.gif';
            image.alt = 'صحيح';
            
            const message = document.createElement('p');
            message.classList.add('feedback-message');
            message.textContent = `لقد حصلت على ${points} نقاط!`;
            
            feedbackContainer.appendChild(title);
            feedbackContainer.appendChild(image);
            feedbackContainer.appendChild(message);
            
            // Show result modal after a short delay
            setTimeout(() => {
                showResultModal(true, points);
            }, 1500);
        } else {
            feedbackContainer.classList.add('show', 'incorrect');
            
            const title = document.createElement('h3');
            title.classList.add('feedback-title');
            title.textContent = 'إجابة خاطئة';
            
            const image = document.createElement('img');
            image.classList.add('feedback-image');
            image.src = 'images/incorrect.gif';
            image.alt = 'خطأ';
            
            const message = document.createElement('p');
            message.classList.add('feedback-message');
            message.textContent = 'حاول مرة أخرى!';
            
            feedbackContainer.appendChild(title);
            feedbackContainer.appendChild(image);
            feedbackContainer.appendChild(message);
            
            // Show result modal after a short delay
            setTimeout(() => {
                showResultModal(false);
            }, 1500);
        }
    }
    
    /**
     * Show the result modal
     * @param {boolean} isCorrect - Whether the answer was correct
     * @param {number} points - Points earned for correct answer
     */
    function showResultModal(isCorrect, points = 0) {
        resultTitle.textContent = isCorrect ? 'إجابة صحيحة!' : 'إجابة خاطئة!';
        resultMessage.textContent = isCorrect 
            ? `لقد حصلت على ${points} نقاط!` 
            : 'لا بأس، حاول مرة أخرى!';
        
        // Set animation image
        resultAnimation.innerHTML = '';
        const img = document.createElement('img');
        img.src = isCorrect ? 'images/celebration.gif' : 'images/try-again.gif';
        img.alt = isCorrect ? 'تهانينا' : 'حاول مرة أخرى';
        img.style.width = '100%';
        resultAnimation.appendChild(img);
        
        // Update button text
        continueBtn.textContent = isCorrect ? 'المتابعة' : 'إعادة المحاولة';
        
        // Show modal
        resultModal.classList.add('show');
    }
    
    /**
     * Go to the next stage or level
     */
    function goToNextStage() {
        resultModal.classList.remove('show');
        
        // Check if we need to go to the next level
        if (gameState.stage >= config.totalStages) {
            gameState.stage = 1;
            gameState.level = Math.min(gameState.level + 1, config.maxLevel);
            
            // Show level completion message if not at max level
            if (gameState.level < config.maxLevel) {
                showAchievementBadge(`لقد أكملت المستوى ${gameState.level - 1} بنجاح!`);
            } else if (gameState.level === config.maxLevel) {
                // At max level, show special completion message
                showAchievementBadge('تهانينا! لقد أكملت جميع المستويات!');
            }
        } else {
            gameState.stage++;
        }
        
        // Update displays
        updateDisplays();
        
        // Start the next stage
        startStage();
    }
    
    /**
     * Update game displays (level, stage, etc.)
     */
    function updateDisplays() {
        levelDisplay.textContent = gameState.level;
        stageDisplay.textContent = gameState.stage;
        totalStagesDisplay.textContent = config.totalStages;
        scoreDisplay.textContent = gameState.score;
    }
    
    /**
     * Update the character speech bubble
     * @param {string} type - Type of message (start, correct, wrong)
     */
    function updateCharacterSpeech(type) {
        const messages = characterMessages[type];
        const randomMessage = messages[Math.floor(Math.random() * messages.length)];
        
        characterSpeech.querySelector('p').textContent = randomMessage;
        
        // Animate speech bubble
        characterSpeech.style.display = 'none';
        setTimeout(() => {
            characterSpeech.style.display = 'block';
            characterSpeech.classList.add('animate-fadeInUp');
            
            setTimeout(() => {
                characterSpeech.classList.remove('animate-fadeInUp');
            }, 500);
        }, 300);
    }
    
    /**
     * Show achievement badge notification
     * @param {string} message - Achievement message to display
     */
    function showAchievementBadge(message) {
        // Create or get achievement badge element
        let badge = document.querySelector('.achievement-badge');
        
        if (!badge) {
            badge = document.createElement('div');
            badge.className = 'achievement-badge';
            
            const badgeIcon = document.createElement('div');
            badgeIcon.className = 'badge-icon';
            
            const badgeContent = document.createElement('div');
            badgeContent.className = 'badge-content';
            
            const badgeTitle = document.createElement('h3');
            badgeTitle.textContent = 'إنجاز جديد!';
            
            const badgeMessage = document.createElement('p');
            
            badgeContent.appendChild(badgeTitle);
            badgeContent.appendChild(badgeMessage);
            
            badge.appendChild(badgeIcon);
            badge.appendChild(badgeContent);
            
            document.body.appendChild(badge);
        }
        
        // Update badge message
        badge.querySelector('p').textContent = message;
        
        // Show badge
        badge.classList.add('show');
        
        // Hide badge after delay
        setTimeout(() => {
            badge.classList.remove('show');
        }, 5000);
    }
    
    // Add game-specific styles
    const style = document.createElement('style');
    style.textContent = `
        .correct-answer {
            border: 4px solid var(--success) !important;
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(46, 204, 113, 0.5);
        }
        
        .wrong-answer {
            border: 4px solid var(--error) !important;
            opacity: 0.7;
        }
        
        @keyframes celebrate {
            0% { transform: scale(0.8); opacity: 0; }
            50% { transform: scale(1.1); opacity: 1; }
            100% { transform: scale(1); opacity: 1; }
        }
    `;
    document.head.appendChild(style);
});