/**
 * Main script for the Educational Game Platform
 * Handles home page functionality
 */

document.addEventListener('DOMContentLoaded', () => {
    // DOM elements
    const levelSelect = document.querySelectorAll('.level-card');
    const startGameBtn = document.getElementById('start-game');
    const showFeedbackBtn = document.getElementById('show-feedback-modal');
    const feedbackModal = document.getElementById('feedback-modal');
    const closeModalBtn = document.querySelector('.close-modal');
    const cancelBtn = document.querySelector('.cancel-btn');
    const feedbackForm = document.getElementById('feedback-form');
    const mascotSpeech = document.getElementById('mascot-speech');
    
    // State variables
    let selectedLevel = 1;
    const speechTexts = [
        'مرحباً بك! هل أنت مستعد للعب والتعلم؟',
        'اختر مستوى واضغط على بدء اللعب!',
        'سوف تستمتع بألعابنا التعليمية الممتعة!',
        'تعلم مع شخصيات اللعبة المرحة!',
        'كل مستوى يحتوي على تحديات جديدة ومثيرة!'
    ];
    
    // Initialize page
    initPage();
    
    /**
     * Initialize the page with event listeners and animations
     */
    function initPage() {
        // Initialize level selection
        levelSelect.forEach(level => {
            level.addEventListener('click', () => {
                if (!level.classList.contains('locked')) {
                    selectLevel(level);
                } else {
                    showLockedLevelMessage(level);
                }
            });
        });
        
        // Start game button event
        startGameBtn.addEventListener('click', startGame);
        
        // Feedback modal events
        showFeedbackBtn.addEventListener('click', openFeedbackModal);
        closeModalBtn.addEventListener('click', closeFeedbackModal);
        cancelBtn.addEventListener('click', closeFeedbackModal);
        
        // Submit feedback form
        feedbackForm.addEventListener('submit', submitFeedback);
        
        // Initialize random mascot speech
        randomizeMascotSpeech();
        setInterval(randomizeMascotSpeech, 8000);
        
        // Show animation for mascot on load
        setTimeout(() => {
            mascotSpeech.style.display = 'block';
            mascotSpeech.classList.add('animate-fadeInUp');
        }, 1000);
    }
    
    /**
     * Select a level
     * @param {HTMLElement} levelEl - The level element that was clicked
     */
    function selectLevel(levelEl) {
        // Remove active class from all levels
        levelSelect.forEach(level => {
            level.classList.remove('active');
        });
        
        // Add active class to selected level
        levelEl.classList.add('active');
        
        // Update selected level
        selectedLevel = levelEl.dataset.level;
    }
    
    /**
     * Show a message for locked levels
     * @param {HTMLElement} levelEl - The locked level element
     */
    function showLockedLevelMessage(levelEl) {
        const levelNum = levelEl.dataset.level;
        
        // Change mascot speech to show locked level message
        mascotSpeech.querySelector('p').textContent = `المستوى ${levelNum} مقفل! أكمل المستويات السابقة أولاً.`;
        mascotSpeech.style.display = 'block';
        
        // Animate mascot speech
        mascotSpeech.classList.remove('animate-fadeInUp');
        void mascotSpeech.offsetWidth; // Trigger reflow
        mascotSpeech.classList.add('animate-fadeInUp');
        
        // Shake the locked level
        levelEl.classList.add('shake');
        setTimeout(() => {
            levelEl.classList.remove('shake');
        }, 500);
    }
    
    /**
     * Start the game with the selected level
     */
    function startGame() {
        window.location.href = `game.html?level=${selectedLevel}`;
    }
    
    /**
     * Open the feedback modal
     */
    function openFeedbackModal() {
        feedbackModal.classList.add('show');
    }
    
    /**
     * Close the feedback modal
     */
    function closeFeedbackModal() {
        feedbackModal.classList.remove('show');
    }
    
    /**
     * Submit the feedback form
     * @param {Event} e - Form submit event
     */
    function submitFeedback(e) {
        e.preventDefault();
        
        // Get form values
        const gameId = document.getElementById('game-select').value;
        const rating = document.querySelector('input[name="rating"]:checked')?.value || 5;
        const feedbackText = document.getElementById('feedback-text').value;
        
        // Validate form
        if (!gameId || !feedbackText) {
            alert('الرجاء ملء جميع الحقول المطلوبة');
            return;
        }
        
        // Here you would normally send data to a server
        console.log('Feedback submitted:', { gameId, rating, feedbackText });
        
        // Show success message
        alert('شكراً لك على تقييمك! لقد تم إرسال ملاحظاتك بنجاح.');
        
        // Close modal and reset form
        closeFeedbackModal();
        feedbackForm.reset();
    }
    
    /**
     * Randomize the mascot speech bubble text
     */
    function randomizeMascotSpeech() {
        const randomText = speechTexts[Math.floor(Math.random() * speechTexts.length)];
        
        if (mascotSpeech.style.display === 'block') {
            // Fade out, change text, then fade in
            mascotSpeech.classList.add('animate-fadeOut');
            
            setTimeout(() => {
                mascotSpeech.querySelector('p').textContent = randomText;
                mascotSpeech.classList.remove('animate-fadeOut');
                mascotSpeech.classList.add('animate-fadeIn');
                
                setTimeout(() => {
                    mascotSpeech.classList.remove('animate-fadeIn');
                }, 500);
            }, 500);
        } else {
            // Just set the text if not visible
            mascotSpeech.querySelector('p').textContent = randomText;
        }
    }
    
    // Add some CSS animations that weren't in the CSS files
    const style = document.createElement('style');
    style.textContent = `
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            50% { transform: translateX(5px); }
            75% { transform: translateX(-5px); }
        }
        
        .shake {
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        
        .animate-fadeOut {
            animation: fadeOut 0.5s ease-out forwards;
        }
        
        .animate-fadeIn {
            animation: fadeIn 0.5s ease-out forwards;
        }
    `;
    document.head.appendChild(style);
});