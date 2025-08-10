/**
 * Stellar Dominion - Main JavaScript File (Optimized)
 *
 * This file contains the shared JavaScript logic for the game pages,
 * including optimized timers, icon initialization, and various form helpers.
 */
document.addEventListener('DOMContentLoaded', () => {
    // This check is a placeholder for a real icon library like Lucide
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // --- Next Turn Timer (Optimized with requestAnimationFrame) ---
    const timerDisplay = document.getElementById('next-turn-timer');
    if (timerDisplay && timerDisplay.dataset.secondsUntilNextTurn) {
        let totalSeconds = parseInt(timerDisplay.dataset.secondsUntilNextTurn) || 0;
        let lastTimestamp = 0;

        function updateTimer(timestamp) {
            if (!lastTimestamp || timestamp - lastTimestamp >= 1000) {
                lastTimestamp = timestamp;

                if (totalSeconds <= 0) {
                    timerDisplay.textContent = "Processing...";
                    setTimeout(() => {
                        // Append a timestamp to prevent caching issues on reload
                        window.location.href = window.location.pathname + '?t=' + new Date().getTime();
                    }, 1500);
                    return; // Stop the loop
                }
                
                totalSeconds--;
                const minutes = Math.floor(totalSeconds / 60);
                const seconds = totalSeconds % 60;
                timerDisplay.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            }
            requestAnimationFrame(updateTimer);
        }
        requestAnimationFrame(updateTimer);
    }

    // --- Dominion Time Clock (Optimized with requestAnimationFrame) ---
    const timeDisplay = document.getElementById('dominion-time');
    if (timeDisplay && timeDisplay.dataset.hours) {
        const initialHours = parseInt(timeDisplay.dataset.hours) || 0;
        const initialMinutes = parseInt(timeDisplay.dataset.minutes) || 0;
        const initialSeconds = parseInt(timeDisplay.dataset.seconds) || 0;
        
        let serverTime = new Date();
        serverTime.setUTCHours(initialHours, initialMinutes, initialSeconds);

        let lastTimestamp = 0;

        function updateClock(timestamp) {
            if (!lastTimestamp || timestamp - lastTimestamp >= 1000) {
                lastTimestamp = timestamp;
                serverTime.setSeconds(serverTime.getSeconds() + 1);
                const hours = String(serverTime.getUTCHours()).padStart(2, '0');
                const minutes = String(serverTime.getUTCMinutes()).padStart(2, '0');
                const seconds = String(serverTime.getUTCSeconds()).padStart(2, '0');
                timeDisplay.textContent = `${hours}:${minutes}:${seconds}`;
            }
            requestAnimationFrame(updateClock);
        }
        requestAnimationFrame(updateClock);
    }

    // --- Point Allocation Form Helper (levels.php) ---
    const availablePointsEl = document.getElementById('available-points');
    const totalSpentEl = document.getElementById('total-spent');
    const pointInputs = document.querySelectorAll('.point-input');
    if (availablePointsEl && totalSpentEl && pointInputs.length > 0) {
        function updateTotal() {
            let total = 0;
            pointInputs.forEach(input => {
                total += parseInt(input.value) || 0;
            });
            totalSpentEl.textContent = total;
            if (total > parseInt(availablePointsEl.textContent)) {
                totalSpentEl.classList.add('text-red-500');
            } else {
                totalSpentEl.classList.remove('text-red-500');
            }
        }
        pointInputs.forEach(input => input.addEventListener('input', updateTotal));
    }

    // --- A.I. Advisor Text Rotator ---
    const advisorTextEl = document.getElementById('advisor-text');
    if (advisorTextEl && advisorTextEl.dataset.advice) {
        const adviceList = JSON.parse(advisorTextEl.dataset.advice || '[]');
        let currentAdviceIndex = 0;
        if (adviceList.length > 1) {
            setInterval(() => {
                currentAdviceIndex = (currentAdviceIndex + 1) % adviceList.length;
                advisorTextEl.style.opacity = 0;
                setTimeout(() => {
                    advisorTextEl.textContent = adviceList[currentAdviceIndex];
                    advisorTextEl.style.opacity = 1;
                }, 500);
            }, 10000);
        }
    }

    // --- Banking Form Helpers (bank.php) ---
    const creditsOnHand = document.getElementById('credits-on-hand');
    const creditsInBank = document.getElementById('credits-in-bank');
    const depositInput = document.getElementById('deposit-amount');
    const withdrawInput = document.getElementById('withdraw-amount');
    const bankPercentBtns = document.querySelectorAll('.bank-percent-btn');
    if (bankPercentBtns.length > 0) {
        bankPercentBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const action = btn.dataset.action;
                const percent = parseFloat(btn.dataset.percent);
                if (action === 'deposit' && creditsOnHand && depositInput) {
                    const amount = Math.floor(parseInt(creditsOnHand.dataset.amount) * percent);
                    depositInput.value = amount;
                } else if (action === 'withdraw' && creditsInBank && withdrawInput) {
                    const amount = Math.floor(parseInt(creditsInBank.dataset.amount) * percent);
                    withdrawInput.value = amount;
                }
            });
        });
    }
    
    // --- Training & Disband Page Logic (battle.php) ---
    const trainTab = document.getElementById('train-tab-content');
    if (trainTab) {
        const trainForm = document.getElementById('train-form');
        const disbandForm = document.getElementById('disband-form');
        const trainTabBtn = document.getElementById('train-tab-btn');
        const disbandTabBtn = document.getElementById('disband-tab-btn');
        const disbandTabContent = document.getElementById('disband-tab-content');

        const availableCitizensEl = document.getElementById('available-citizens');
        const availableCreditsEl = document.getElementById('available-credits');
        const totalCostEl = document.getElementById('total-build-cost');
        const totalRefundEl = document.getElementById('total-refund-value');

        const availableCitizens = parseInt(availableCitizensEl.dataset.amount);
        const availableCredits = parseInt(availableCreditsEl.dataset.amount);
        const charismaDiscount = parseFloat(trainForm.dataset.charismaDiscount);
        const refundRate = 0.75;

        // --- Tab Switching ---
        const activeClasses = ['bg-gray-700', 'text-white', 'font-semibold'];
        const inactiveClasses = ['bg-gray-800', 'hover:bg-gray-700', 'text-gray-400'];

        trainTabBtn.addEventListener('click', () => {
            trainTab.classList.remove('hidden');
            disbandTabContent.classList.add('hidden');
            trainTabBtn.classList.add(...activeClasses);
            trainTabBtn.classList.remove(...inactiveClasses);
            disbandTabBtn.classList.add(...inactiveClasses);
            disbandTabBtn.classList.remove(...activeClasses);
        });

        disbandTabBtn.addEventListener('click', () => {
            disbandTabContent.classList.remove('hidden');
            trainTab.classList.add('hidden');
            disbandTabBtn.classList.add(...activeClasses);
            disbandTabBtn.classList.remove(...inactiveClasses);
            trainTabBtn.classList.add(...inactiveClasses);
            trainTabBtn.classList.remove(...activeClasses);
        });

        const trainInputs = trainForm.querySelectorAll('.unit-input-train');
        function updateTrainingCost() {
            let totalCost = 0;
            let totalCitizens = 0;
            trainInputs.forEach(input => {
                const amount = parseInt(input.value) || 0;
                totalCitizens += amount;
                if (amount > 0) {
                    const baseCost = parseInt(input.dataset.cost);
                    totalCost += amount * Math.floor(baseCost * charismaDiscount);
                }
            });
            totalCostEl.textContent = totalCost.toLocaleString();
            totalCostEl.classList.toggle('text-red-500', totalCost > availableCredits);
            availableCitizensEl.classList.toggle('text-red-500', totalCitizens > availableCitizens);
        }
        trainInputs.forEach(input => input.addEventListener('input', updateTrainingCost));
        
        trainForm.querySelectorAll('.train-max-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                const clickedInput = e.currentTarget.previousElementSibling;
                let otherInputsCost = 0;
                let otherInputsCitizens = 0;
                trainInputs.forEach(input => {
                    if (input !== clickedInput) {
                        const amount = parseInt(input.value) || 0;
                        otherInputsCitizens += amount;
                        if (amount > 0) {
                            const baseCost = parseInt(input.dataset.cost);
                            otherInputsCost += amount * Math.floor(baseCost * charismaDiscount);
                        }
                    }
                });

                const remainingCredits = availableCredits - otherInputsCost;
                const remainingCitizens = availableCitizens - otherInputsCitizens;
                const baseCost = parseInt(clickedInput.dataset.cost);
                const discountedCost = Math.floor(baseCost * charismaDiscount);
                const maxByCredits = discountedCost > 0 ? Math.floor(remainingCredits / discountedCost) : Infinity;
                const maxForThisUnit = Math.max(0, Math.min(maxByCredits, remainingCitizens));
                
                clickedInput.value = maxForThisUnit;
                updateTrainingCost();
            });
        });

        const disbandInputs = disbandForm.querySelectorAll('.unit-input-disband');
        function updateDisbandRefund() {
            let totalRefund = 0;
            disbandInputs.forEach(input => {
                const amount = parseInt(input.value) || 0;
                if (amount > 0) {
                    const baseCost = parseInt(input.dataset.cost);
                    totalRefund += amount * Math.floor(baseCost * refundRate);
                }
            });
            totalRefundEl.textContent = totalRefund.toLocaleString();
        }
        disbandInputs.forEach(input => input.addEventListener('input', updateDisbandRefund));

        disbandForm.querySelectorAll('.disband-max-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                const input = e.currentTarget.previousElementSibling;
                input.value = input.max;
                updateDisbandRefund();
            });
        });
        
        updateTrainingCost();
        updateDisbandRefund();
    }

    // --- Armory Purchase Calculator (armory.php) ---
    const armoryForm = document.getElementById('armory-form');
    if (armoryForm) {
        const summaryItemsEl = document.getElementById('summary-items');
        const grandTotalEl = document.getElementById('grand-total');
        const quantityInputs = armoryForm.querySelectorAll('.armory-item-quantity');

        const updateArmoryCost = () => {
            let grandTotal = 0;
            let summaryHtml = '';

            quantityInputs.forEach(input => {
                const quantity = parseInt(input.value) || 0;
                const itemRow = input.closest('.armory-item');
                const costEl = itemRow.querySelector('[data-cost]');
                const subtotalEl = itemRow.querySelector('.subtotal');
                
                const cost = parseInt(costEl.dataset.cost);
                const subtotal = quantity * cost;
                
                if (subtotalEl) {
                    subtotalEl.textContent = subtotal.toLocaleString();
                }

                grandTotal += subtotal;

                if (quantity > 0) {
                    const itemName = input.dataset.itemName;
                    summaryHtml += `
                        <div class="flex justify-between">
                            <span>${itemName} x${quantity}</span>
                            <span class="font-semibold">${subtotal.toLocaleString()}</span>
                        </div>`;
                }
            });

            grandTotalEl.textContent = grandTotal.toLocaleString();
            
            if (summaryHtml === '') {
                summaryItemsEl.innerHTML = '<p class="text-gray-500 italic">Select items to purchase...</p>';
            } else {
                summaryItemsEl.innerHTML = summaryHtml;
            }
        };

        quantityInputs.forEach(input => {
            input.addEventListener('input', updateArmoryCost);
        });
        
        updateArmoryCost();
    }

    // --- Advisor Mobile Toggle ---
    const toggleButton = document.getElementById('toggle-advisor-btn');
    const advisorContainer = document.querySelector('.advisor-container');

    if (toggleButton && advisorContainer) {
        toggleButton.addEventListener('click', function() {
            advisorContainer.classList.toggle('advisor-minimized');
            if (advisorContainer.classList.contains('advisor-minimized')) {
                toggleButton.textContent = '+';
            } else {
                toggleButton.textContent = '-';
            }
        });
    }
});
