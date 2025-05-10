/**
 * Progress tracking script for the Educational Game Platform
 * Handles progress charts and statistics
 */

document.addEventListener('DOMContentLoaded', () => {
    // DOM elements
    const childSelection = document.getElementById('child-selection');
    const childIdInput = document.getElementById('child-id-input');
    const selectChildBtn = document.getElementById('select-child-btn');
    const childProfiles = document.getElementById('child-profiles');
    const gameSelection = document.getElementById('game-selection');
    const gameCards = document.querySelectorAll('.game-card');
    const chartsSection = document.getElementById('charts-section');
    const selectedGameName = document.getElementById('selected-game-name');
    const comparisonSection = document.getElementById('comparison-section');
    const comparisonBody = document.getElementById('comparison-body');
    const totalPoints = document.getElementById('total-points');
    const totalTime = document.getElementById('total-time');
    const totalAttempts = document.getElementById('total-attempts');
    const accuracyPercentage = document.getElementById('accuracy-percentage');
    
    // Chart instance
    let progressChart = null;
    
    // Progress data (for demo - this would normally come from a database)
    const progressData = {
        1: { // Child ID 1
            1: { // Game ID 1
                scores: { 1: 24, 2: 18, 3: 9 },
                times: { 1: 120, 2: 180, 3: 240 },
                tries: { 1: 30, 2: 25, 3: 15 },
                maxScores: { 1: 30, 2: 30, 3: 30 },
                targetMaxTimes: { 1: 180, 2: 240, 3: 300 }
            },
            2: { // Game ID 2
                scores: { 1: 18, 2: 12, 3: 6 },
                times: { 1: 90, 2: 150, 3: 210 },
                tries: { 1: 20, 2: 18, 3: 10 },
                maxScores: { 1: 20, 2: 20, 3: 20 },
                targetMaxTimes: { 1: 150, 2: 210, 3: 270 }
            }
        },
        2: { // Child ID 2
            1: { // Game ID 1
                scores: { 1: 27, 2: 21, 3: 12 },
                times: { 1: 150, 2: 210, 3: 270 },
                tries: { 1: 35, 2: 30, 3: 20 },
                maxScores: { 1: 30, 2: 30, 3: 30 },
                targetMaxTimes: { 1: 180, 2: 240, 3: 300 }
            }
        }
    };
    
    // Current state
    let currentChildId = null;
    let currentGameId = 1;
    
    // Initialize page
    initPage();
    
    /**
     * Initialize the page with event listeners
     */
    function initPage() {
        // Child selection events
        selectChildBtn.addEventListener('click', selectChild);
        
        // Child profile selection
        const profiles = document.querySelectorAll('.profile-card');
        profiles.forEach(profile => {
            profile.addEventListener('click', () => {
                profiles.forEach(p => p.classList.remove('active'));
                profile.classList.add('active');
                currentChildId = parseInt(profile.querySelector('.avatar-placeholder').textContent);
                showGameSelection();
            });
        });
        
        // Game selection events
        gameCards.forEach(card => {
            card.addEventListener('click', () => {
                gameCards.forEach(c => c.classList.remove('active'));
                card.classList.add('active');
                currentGameId = parseInt(card.dataset.gameId);
                selectedGameName.textContent = card.querySelector('.game-name').textContent;
                showProgressData();
            });
        });
        
        // Hide sections initially
        gameSelection.style.display = 'none';
        chartsSection.style.display = 'none';
        comparisonSection.style.display = 'none';
        
        // Check URL parameters for direct loading
        const urlParams = new URLSearchParams(window.location.search);
        const childIdParam = urlParams.get('childId');
        const gameIdParam = urlParams.get('gameId');
        
        if (childIdParam && gameIdParam) {
            currentChildId = parseInt(childIdParam);
            currentGameId = parseInt(gameIdParam);
            childIdInput.value = currentChildId;
            
            // Auto-select profile and game
            selectChild();
            const gameCard = document.querySelector(`.game-card[data-game-id="${currentGameId}"]`);
            if (gameCard) {
                gameCards.forEach(c => c.classList.remove('active'));
                gameCard.classList.add('active');
                selectedGameName.textContent = gameCard.querySelector('.game-name').textContent;
                showProgressData();
            }
        }
    }
    
    /**
     * Handle child selection
     */
    function selectChild() {
        const childId = parseInt(childIdInput.value);
        
        if (isNaN(childId) || childId <= 0) {
            alert('الرجاء إدخال رقم طفل صحيح!');
            return;
        }
        
        // Check if child exists in data
        if (!progressData[childId]) {
            // For demo purposes, we'll create dummy data for new children
            progressData[childId] = {
                1: {
                    scores: { 1: 0, 2: 0, 3: 0 },
                    times: { 1: 0, 2: 0, 3: 0 },
                    tries: { 1: 0, 2: 0, 3: 0 },
                    maxScores: { 1: 30, 2: 30, 3: 30 },
                    targetMaxTimes: { 1: 180, 2: 240, 3: 300 }
                }
            };
        }
        
        currentChildId = childId;
        
        // Update profiles
        updateChildProfiles(childId);
        
        // Show game selection
        showGameSelection();
    }
    
    /**
     * Update child profiles display
     * @param {number} selectedChildId - The selected child ID
     */
    function updateChildProfiles(selectedChildId) {
        childProfiles.innerHTML = '';
        
        // Add the selected child
        const profileCard = document.createElement('div');
        profileCard.className = 'profile-card active';
        
        const profileAvatar = document.createElement('div');
        profileAvatar.className = 'profile-avatar';
        
        const avatarPlaceholder = document.createElement('span');
        avatarPlaceholder.className = 'avatar-placeholder';
        avatarPlaceholder.textContent = selectedChildId;
        
        const profileInfo = document.createElement('div');
        profileInfo.className = 'profile-info';
        
        const profileName = document.createElement('h3');
        profileName.className = 'profile-name';
        profileName.textContent = `طفل #${selectedChildId}`;
        
        const profileDetails = document.createElement('p');
        profileDetails.className = 'profile-details';
        profileDetails.textContent = 'آخر نشاط: اليوم';
        
        profileAvatar.appendChild(avatarPlaceholder);
        profileInfo.appendChild(profileName);
        profileInfo.appendChild(profileDetails);
        
        profileCard.appendChild(profileAvatar);
        profileCard.appendChild(profileInfo);
        
        childProfiles.appendChild(profileCard);
    }
    
    /**
     * Show game selection section
     */
    function showGameSelection() {
        childSelection.style.display = 'block';
        gameSelection.style.display = 'block';
        chartsSection.style.display = 'none';
        comparisonSection.style.display = 'none';
        
        // Reset game selection
        gameCards.forEach((card, index) => {
            if (index === 0) {
                card.classList.add('active');
                currentGameId = parseInt(card.dataset.gameId);
            } else {
                card.classList.remove('active');
            }
        });
    }
    
    /**
     * Show progress data for selected child and game
     */
    function showProgressData() {
        chartsSection.style.display = 'block';
        comparisonSection.style.display = 'block';
        
        // Get progress data
        const childData = progressData[currentChildId];
        
        if (!childData || !childData[currentGameId]) {
            // Initialize empty data for this game if it doesn't exist
            if (!childData) {
                progressData[currentChildId] = {};
            }
            progressData[currentChildId][currentGameId] = {
                scores: { 1: 0, 2: 0, 3: 0 },
                times: { 1: 0, 2: 0, 3: 0 },
                tries: { 1: 0, 2: 0, 3: 0 },
                maxScores: { 1: 30, 2: 30, 3: 30 },
                targetMaxTimes: { 1: 180, 2: 240, 3: 300 }
            };
        }
        
        const gameData = childData[currentGameId];
        
        // Calculate statistics
        const totalPointsValue = Object.values(gameData.scores).reduce((a, b) => a + b, 0);
        const totalTimeValue = Math.round(Object.values(gameData.times).reduce((a, b) => a + b, 0) / 60); // Convert to minutes
        const totalAttemptsValue = Object.values(gameData.tries).reduce((a, b) => a + b, 0);
        
        // Calculate accuracy
        const maxTotalScore = Object.values(gameData.maxScores).reduce((a, b) => a + b, 0);
        const accuracyValue = maxTotalScore > 0 ? Math.round((totalPointsValue / maxTotalScore) * 100) : 0;
        
        // Update statistics display
        totalPoints.textContent = totalPointsValue;
        totalTime.textContent = totalTimeValue;
        totalAttempts.textContent = totalAttemptsValue;
        accuracyPercentage.textContent = accuracyValue;
        
        // Create comparison data
        updateComparisonTable(gameData);
        
        // Create/update chart
        createProgressChart(gameData);
    }
    
    /**
     * Update the comparison table with game data
     * @param {Object} gameData - Game progress data
     */
    function updateComparisonTable(gameData) {
        comparisonBody.innerHTML = '';
        
        // Create comparison data for each level
        for (let level = 1; level <= 3; level++) {
            const score = gameData.scores[level] || 0;
            const maxScore = gameData.maxScores[level] || 30;
            const scorePercentage = maxScore > 0 ? Math.round((score / maxScore) * 100) : 0;
            
            const time = gameData.times[level] || 0;
            const targetTime = gameData.targetMaxTimes[level] || 180;
            const timePercentage = targetTime > 0 ? Math.round((time / targetTime) * 100) : 0;
            
            // Create table row
            const row = document.createElement('tr');
            
            // Add cells to row
            row.innerHTML = `
                <td>المستوى ${level}</td>
                <td>${score}</td>
                <td>${maxScore}</td>
                <td>${scorePercentage}%</td>
                <td>${time}</td>
                <td>${targetTime}</td>
                <td>${timePercentage}%</td>
            `;
            
            // Add row to table
            comparisonBody.appendChild(row);
        }
    }
    
    /**
     * Create or update the progress chart
     * @param {Object} gameData - Game progress data
     */
    function createProgressChart(gameData) {
        const ctx = document.getElementById('progress-chart').getContext('2d');
        
        // Destroy existing chart if it exists
        if (progressChart) {
            progressChart.destroy();
        }
        
        // Set up chart data
        const chartData = {
            labels: ['المستوى 1', 'المستوى 2', 'المستوى 3'],
            datasets: [
                {
                    label: 'النقاط المكتسبة',
                    data: [gameData.scores[1] || 0, gameData.scores[2] || 0, gameData.scores[3] || 0],
                    backgroundColor: 'rgba(108, 99, 255, 0.7)',
                    borderColor: 'rgba(108, 99, 255, 1)',
                    borderWidth: 1
                },
                {
                    label: 'النقاط القصوى',
                    type: 'line',
                    data: [gameData.maxScores[1], gameData.maxScores[2], gameData.maxScores[3]],
                    borderColor: 'rgba(255, 159, 67, 1)',
                    backgroundColor: 'rgba(0, 0, 0, 0)',
                    borderWidth: 2,
                    fill: false,
                    pointRadius: 5,
                    pointBackgroundColor: 'rgba(255, 159, 67, 1)'
                },
                {
                    label: 'الوقت المستغرق (ثواني)',
                    data: [gameData.times[1] || 0, gameData.times[2] || 0, gameData.times[3] || 0],
                    backgroundColor: 'rgba(78, 205, 196, 0.7)',
                    borderColor: 'rgba(78, 205, 196, 1)',
                    borderWidth: 1
                },
                {
                    label: 'الوقت المستهدف الأقصى (ثواني)',
                    type: 'line',
                    data: [gameData.targetMaxTimes[1], gameData.targetMaxTimes[2], gameData.targetMaxTimes[3]],
                    borderColor: 'rgba(46, 204, 113, 1)',
                    backgroundColor: 'rgba(0, 0, 0, 0)',
                    borderWidth: 2,
                    fill: false,
                    pointRadius: 5,
                    pointBackgroundColor: 'rgba(46, 204, 113, 1)'
                },
                {
                    label: 'عدد المحاولات',
                    data: [gameData.tries[1] || 0, gameData.tries[2] || 0, gameData.tries[3] || 0],
                    backgroundColor: 'rgba(255, 107, 107, 0.7)',
                    borderColor: 'rgba(255, 107, 107, 1)',
                    borderWidth: 1
                }
            ]
        };
        
        // Calculate max Y value for chart scale
        const allValues = [
            ...Object.values(gameData.scores),
            ...Object.values(gameData.maxScores),
            ...Object.values(gameData.times),
            ...Object.values(gameData.targetMaxTimes),
            ...Object.values(gameData.tries)
        ];
        const maxValue = Math.max(...allValues);
        const yAxisMax = Math.ceil(maxValue * 1.1); // Add 10% buffer
        
        // Create chart
        progressChart = new Chart(ctx, {
            type: 'bar',
            data: chartData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: yAxisMax,
                        title: {
                            display: true,
                            text: 'القيمة'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'المستويات'
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                }
            }
        });
    }
});