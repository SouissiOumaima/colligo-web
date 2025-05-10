/**
 * Animation script for the Educational Game Platform
 * Handles advanced animations and visual effects
 */

document.addEventListener('DOMContentLoaded', () => {
    // Init animations when page loads
    initAnimations();
    
    /**
     * Initialize all animations on the page
     */
    function initAnimations() {
        // Create floating animation for UI elements
        animateFloatingElements();
        
        // Create hover effects for interactive elements
        createHoverEffects();
        
        // Add ripple effect to buttons
        addRippleEffect();
        
        // Add animation for mascot/character
        animateCharacter();
        
        // Initialize achievement badges (if any)
        initAchievementBadges();
    }
    
    /**
     * Animate floating UI elements
     */
    function animateFloatingElements() {
        const floatingElements = document.querySelectorAll('.animate-float, .animate-float-slow');
        
        floatingElements.forEach(element => {
            // Add random initial position to make animations out of sync
            const delay = Math.random() * -12; // Random start time
            element.style.animationDelay = `${delay}s`;
        });
    }
    
    /**
     * Create hover effects for interactive elements
     */
    function createHoverEffects() {
        // Level cards hover effect
        const levelCards = document.querySelectorAll('.level-card:not(.locked)');
        levelCards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                // Add tilt effect on hover
                card.style.transform = 'translateY(-5px) rotate(1deg)';
            });
            
            card.addEventListener('mouseleave', () => {
                // Reset transform on mouse leave
                card.style.transform = '';
            });
        });
        
        // Game cards hover effect
        const gameCards = document.querySelectorAll('.game-card-item');
        gameCards.forEach(card => {
            card.addEventListener('mouseenter', () => {
                // Scale up and add glow on hover
                card.style.transform = 'scale(1.03)';
                card.style.boxShadow = '0 8px 20px rgba(0, 0, 0, 0.15), 0 0 15px rgba(108, 99, 255, 0.3)';
            });
            
            card.addEventListener('mouseleave', () => {
                // Reset styles on mouse leave
                card.style.transform = '';
                card.style.boxShadow = '';
            });
        });
    }
    
    /**
     * Add ripple effect to buttons
     */
    function addRippleEffect() {
        const buttons = document.querySelectorAll('.btn');
        
        buttons.forEach(button => {
            button.addEventListener('click', function(e) {
                // Create ripple element
                const ripple = document.createElement('span');
                ripple.classList.add('ripple');
                this.appendChild(ripple);
                
                // Get position
                const rect = button.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                
                // Set size and position
                ripple.style.width = ripple.style.height = `${size}px`;
                ripple.style.left = `${e.clientX - rect.left - size / 2}px`;
                ripple.style.top = `${e.clientY - rect.top - size / 2}px`;
                
                // Remove after animation completes
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });
        
        // Add ripple style if not already in the document
        if (!document.getElementById('ripple-style')) {
            const style = document.createElement('style');
            style.id = 'ripple-style';
            style.textContent = `
                .btn {
                    position: relative;
                    overflow: hidden;
                }
                
                .ripple {
                    position: absolute;
                    border-radius: 50%;
                    background-color: rgba(255, 255, 255, 0.7);
                    transform: scale(0);
                    animation: ripple 0.6s linear;
                    pointer-events: none;
                }
                
                @keyframes ripple {
                    to {
                        transform: scale(2);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(style);
        }
    }
    
    /**
     * Animate mascot/character element
     */
    function animateCharacter() {
        const mascot = document.querySelector('.mascot');
        const gameCharacter = document.querySelector('.game-character');
        
        // Animate mascot if present
        if (mascot) {
            // Add small random movements to make it more lively
            setInterval(() => {
                const randomX = (Math.random() - 0.5) * 5;
                const randomY = (Math.random() - 0.5) * 5;
                const randomRotate = (Math.random() - 0.5) * 3;
                
                mascot.style.transform = `translate(${randomX}px, ${randomY}px) rotate(${randomRotate}deg)`;
                
                setTimeout(() => {
                    mascot.style.transform = '';
                }, 500);
            }, 3000);
        }
        
        // Animate game character if present
        if (gameCharacter) {
            // Make character react to mouse proximity
            document.addEventListener('mousemove', (e) => {
                const characterRect = gameCharacter.getBoundingClientRect();
                const characterX = characterRect.left + characterRect.width / 2;
                const characterY = characterRect.top + characterRect.height / 2;
                
                const mouseX = e.clientX;
                const mouseY = e.clientY;
                
                // Calculate distance
                const distance = Math.sqrt(
                    Math.pow(mouseX - characterX, 2) + 
                    Math.pow(mouseY - characterY, 2)
                );
                
                // If mouse is close, make character look at mouse
                if (distance < 300) {
                    const angle = Math.atan2(mouseY - characterY, mouseX - characterX);
                    const lookAngle = angle * (180 / Math.PI);
                    
                    // Limit rotation to a small range to make it look natural
                    const limitedAngle = Math.max(-15, Math.min(15, lookAngle / 10));
                    
                    gameCharacter.style.transform = `rotate(${limitedAngle}deg)`;
                } else {
                    gameCharacter.style.transform = '';
                }
            });
        }
    }
    
    /**
     * Initialize achievement badges
     */
    function initAchievementBadges() {
        // Check if there are any triggered badges
        const achievementBadge = document.querySelector('.achievement-badge');
        
        if (achievementBadge) {
            // Add close functionality
            achievementBadge.addEventListener('click', () => {
                achievementBadge.classList.remove('show');
            });
            
            // Auto hide after 5 seconds
            if (achievementBadge.classList.contains('show')) {
                setTimeout(() => {
                    achievementBadge.classList.remove('show');
                }, 5000);
            }
        }
    }
    
    // Add parallax effect to background elements based on mouse movement
    if (document.querySelector('.bg-animation')) {
        document.addEventListener('mousemove', (e) => {
            const clouds = document.querySelectorAll('.cloud');
            
            // Get mouse position
            const mouseX = e.clientX / window.innerWidth;
            const mouseY = e.clientY / window.innerHeight;
            
            // Move clouds based on mouse position
            clouds.forEach((cloud, index) => {
                const depth = (index + 1) * 10;
                const moveX = (mouseX - 0.5) * depth;
                const moveY = (mouseY - 0.5) * depth / 2;
                
                cloud.style.transform = `translate3d(${moveX}px, ${moveY}px, 0)`;
            });
            
            // Move sun/rainbow if present
            const sun = document.querySelector('.sun');
            if (sun) {
                const sunMoveX = (mouseX - 0.5) * -15;
                const sunMoveY = (mouseY - 0.5) * -15;
                sun.style.transform = `translate3d(${sunMoveX}px, ${sunMoveY}px, 0)`;
            }
            
            const rainbow = document.querySelector('.rainbow');
            if (rainbow) {
                const rainbowMoveX = (mouseX - 0.5) * -25;
                const rainbowMoveY = (mouseY - 0.5) * -10;
                rainbow.style.transform = `translate3d(${rainbowMoveX}px, ${rainbowMoveY}px, 0)`;
            }
        });
    }
});