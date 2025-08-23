/**
 * Stellar Dominion - Main JavaScript File (Optimized)
 *
 * This version includes the final, correct logic for the Armory "Max" buttons.
 */


document.addEventListener('DOMContentLoaded', () => {
    // Icon init (keep reference)
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // ---------- Utilities ----------
    const toInt = (v, def = 0) => {
        const n = parseInt(v, 10);
        return Number.isFinite(n) ? n : def;
    };
    const toFloat = (v, def = 0) => {
        const n = parseFloat(v);
        return Number.isFinite(n) ? n : def;
    };

    // Global 1Hz ticker using requestAnimationFrame
    const secondCallbacks = [];
    let rafId = 0, lastTs = 0;
    const tick = (ts) => {
        if (!lastTs) lastTs = ts;
        if (ts - lastTs >= 1000) {
            lastTs += 1000;
            for (let i = 0; i < secondCallbacks.length; i++) {
                try { secondCallbacks[i](); } catch (_) {}
            }
        }
        rafId = requestAnimationFrame(tick);
    };
    const startTicker = () => { if (!rafId) rafId = requestAnimationFrame(tick); };
    const stopTicker  = () => { if (rafId) cancelAnimationFrame(rafId); rafId = 0; lastTs = 0; };
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) stopTicker(); else startTicker();
    });
    startTicker();

    // ---------- Next Turn Timer (rAF 1Hz) ----------
    const timerDisplay = document.getElementById('next-turn-timer');
    if (timerDisplay && timerDisplay.dataset.secondsUntilNextTurn) {
        let totalSeconds = toInt(timerDisplay.dataset.secondsUntilNextTurn, 0);
        const onSecond = () => {
            if (totalSeconds <= 0) {
                timerDisplay.textContent = "Processing...";
                setTimeout(() => {
                    window.location.href = window.location.pathname + '?t=' + Date.now();
                }, 1500);
                const idx = secondCallbacks.indexOf(onSecond);
                if (idx > -1) secondCallbacks.splice(idx, 1);
                return;
            }
            totalSeconds--;
            const m = Math.floor(totalSeconds / 60);
            const s = totalSeconds % 60;
            timerDisplay.textContent = `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        };
        secondCallbacks.push(onSecond);
    }

    // ---------- Dominion Time Clock (rAF 1Hz) ----------
    const timeDisplay = document.getElementById('dominion-time');
    if (timeDisplay && timeDisplay.dataset.hours) {
        const h0 = toInt(timeDisplay.dataset.hours, 0);
        const m0 = toInt(timeDisplay.dataset.minutes, 0);
        const s0 = toInt(timeDisplay.dataset.seconds, 0);

        const serverTime = new Date();
        serverTime.setUTCHours(h0, m0, s0, 0);

        const onSecond = () => {
            serverTime.setSeconds(serverTime.getSeconds() + 1);
            const hh = String(serverTime.getUTCHours()).padStart(2, '0');
            const mm = String(serverTime.getUTCMinutes()).padStart(2, '0');
            const ss = String(serverTime.getUTCSeconds()).padStart(2, '0');
            timeDisplay.textContent = `${hh}:${mm}:${ss}`;
        };
        secondCallbacks.push(onSecond);
    }

    // ---------- Next Deposit Timer (bank.php) — setInterval (drift-corrected) ----------
    const depositTimerDisplay = document.getElementById('next-deposit-timer');
    if (depositTimerDisplay && depositTimerDisplay.dataset.seconds) {
        let totalSeconds = toInt(depositTimerDisplay.dataset.seconds, 0);
        const render = (secs) => {
            if (secs <= 0) {
                depositTimerDisplay.textContent = "Available";
                return;
            }
            const h = Math.floor(secs / 3600);
            const m = Math.floor((secs % 3600) / 60);
            const s = secs % 60;
            depositTimerDisplay.textContent =
                `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        };
        render(totalSeconds);
        if (totalSeconds > 0) {
            const endAt = Date.now() + totalSeconds * 1000;
            const iv = setInterval(() => {
                const remaining = Math.max(0, Math.round((endAt - Date.now()) / 1000));
                render(remaining);
                if (remaining <= 0) {
                    clearInterval(iv);
                    setTimeout(() => window.location.reload(), 1500);
                }
            }, 1000);
        }
    }

    // ---------- Peace Treaty Timers (attack.php) ----------
    document.querySelectorAll('.peace-timer').forEach(el => el.remove());

    // ---------- Point Allocation Form (levels.php) ----------
    const availablePointsEl = document.getElementById('available-points');
    const totalSpentEl = document.getElementById('total-spent');
    const pointInputs = document.querySelectorAll('.point-input');
    if (availablePointsEl && totalSpentEl && pointInputs.length) {
        const getAvail = () => toInt(availablePointsEl.textContent, 0);
        let debounceId = 0;
        const updateTotal = () => {
            let total = 0;
            for (let i = 0; i < pointInputs.length; i++) {
                total += toInt(pointInputs[i].value, 0);
            }
            totalSpentEl.textContent = String(total);
            totalSpentEl.classList.toggle('text-red-500', total > getAvail());
        };
        const onInput = () => {
            clearTimeout(debounceId);
            debounceId = setTimeout(updateTotal, 50);
        };
        pointInputs.forEach(input => input.addEventListener('input', onInput, { passive: true }));
    }

    // ---------- A.I. Advisor Text Rotator ----------
    const advisorTextEl = document.getElementById('advisor-text');
    if (advisorTextEl && advisorTextEl.dataset.advice) {
        let adviceList = [];
        try {
            adviceList = JSON.parse(advisorTextEl.dataset.advice || '[]');
            if (!Array.isArray(adviceList)) adviceList = [];
        } catch (_) { adviceList = []; }
        let idx = 0;
        if (adviceList.length > 1) {
            setInterval(() => {
                idx = (idx + 1) % adviceList.length;
                advisorTextEl.style.opacity = 0;
                setTimeout(() => {
                    advisorTextEl.textContent = String(adviceList[idx]);
                    advisorTextEl.style.opacity = 1;
                }, 500);
            }, 10000);
        }
    }

    // ---------- Banking Form Helpers (bank.php) ----------
    const creditsOnHand = document.getElementById('credits-on-hand');
    const creditsInBank = document.getElementById('credits-in-bank');
    const depositInput = document.getElementById('deposit-amount');
    const withdrawInput = document.getElementById('withdraw-amount');
    const bankPercentBtns = document.querySelectorAll('.bank-percent-btn');
    if (bankPercentBtns.length) {
        bankPercentBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const action = btn.dataset.action;
                const percent = toFloat(btn.dataset.percent, 0);
                if (action === 'deposit' && creditsOnHand && depositInput) {
                    const base = toInt(creditsOnHand.dataset.amount, 0);
                    depositInput.value = Math.floor(base * percent);
                } else if (action === 'withdraw' && creditsInBank && withdrawInput) {
                    const base = toInt(creditsInBank.dataset.amount, 0);
                    withdrawInput.value = Math.floor(base * percent);
                }
            }, { passive: true });
        });
    }

    // ---------- Training & Disband (battle.php) ----------
    const trainTab = document.getElementById('train-tab-content');
    if (trainTab) {
        const trainForm         = document.getElementById('train-form');
        const disbandForm       = document.getElementById('disband-form');
        const trainTabBtn       = document.getElementById('train-tab-btn');
        const disbandTabBtn     = document.getElementById('disband-tab-btn');
        const disbandTabContent = document.getElementById('disband-tab-content');

        const availableCitizensEl = document.getElementById('available-citizens');
        const availableCreditsEl  = document.getElementById('available-credits');
        const totalCostEl   = document.getElementById('total-build-cost');
        const totalRefundEl = document.getElementById('total-refund-value');

        const availableCitizens  = toInt(availableCitizensEl?.dataset.amount, 0);
        const availableCredits   = toInt(availableCreditsEl?.dataset.amount, 0);
        const charismaDiscount   = toFloat(trainForm?.dataset.charismaDiscount, 1);
        const refundRate         = 0.75;

        // Tab switching
        const activeClasses = ['bg-gray-700', 'text-white', 'font-semibold'];
        const inactiveClasses = ['bg-gray-800', 'hover:bg-gray-700', 'text-gray-400'];

        const setActive = (btnOn, btnOff, contentOn, contentOff) => {
            contentOn.classList.remove('hidden');
            contentOff.classList.add('hidden');
            btnOn.classList.add(...activeClasses);
            btnOn.classList.remove(...inactiveClasses);
            btnOff.classList.add(...inactiveClasses);
            btnOff.classList.remove(...activeClasses);
        };
        trainTabBtn?.addEventListener('click', () => setActive(trainTabBtn, disbandTabBtn, trainTab, disbandTabContent));
        disbandTabBtn?.addEventListener('click', () => setActive(disbandTabBtn, trainTabBtn, disbandTabContent, trainTab));

        // Training costs
        const trainInputs = trainForm ? trainForm.querySelectorAll('.unit-input-train') : [];
        const updateTrainingCost = () => {
            let totalCost = 0;
            let totalCitizens = 0;
            trainInputs.forEach(input => {
                const amount = toInt(input.value, 0);
                totalCitizens += amount;
                if (amount > 0) {
                    const baseCost = toInt(input.dataset.cost, 0);
                    totalCost += amount * Math.floor(baseCost * charismaDiscount);
                }
            });
            totalCostEl.textContent = totalCost.toLocaleString();
            totalCostEl.classList.toggle('text-red-500', totalCost > availableCredits);
            availableCitizensEl.classList.toggle('text-red-500', totalCitizens > availableCitizens);
        };
        trainInputs.forEach(input => input.addEventListener('input', updateTrainingCost, { passive: true }));

        trainForm?.querySelectorAll('.train-max-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const clickedInput = e.currentTarget.previousElementSibling;
                let otherCost = 0, otherCitizens = 0;

                trainInputs.forEach(input => {
                    if (input !== clickedInput) {
                        const amt = toInt(input.value, 0);
                        otherCitizens += amt;
                        if (amt > 0) {
                            otherCost += amt * Math.floor(toInt(input.dataset.cost, 0) * charismaDiscount);
                        }
                    }
                });

                const remainingCredits  = Math.max(0, availableCredits - otherCost);
                const remainingCitizens = Math.max(0, availableCitizens - otherCitizens);
                const baseCost = toInt(clickedInput.dataset.cost, 0);
                const discCost = Math.floor(baseCost * charismaDiscount);
                const maxByCredits = discCost > 0 ? Math.floor(remainingCredits / discCost) : remainingCitizens;
                const maxForThis = Math.max(0, Math.min(maxByCredits, remainingCitizens));

                clickedInput.value = maxForThis;
                updateTrainingCost();
            });
        });

        // Disband refunds
        const disbandInputs = disbandForm ? disbandForm.querySelectorAll('.unit-input-disband') : [];
        const updateDisbandRefund = () => {
            let totalRefund = 0;
            disbandInputs.forEach(input => {
                const amt = toInt(input.value, 0);
                if (amt > 0) {
                    totalRefund += amt * Math.floor(toInt(input.dataset.cost, 0) * refundRate);
                }
            });
            totalRefundEl.textContent = totalRefund.toLocaleString();
        };
        disbandInputs.forEach(input => input.addEventListener('input', updateDisbandRefund, { passive: true }));
        disbandForm?.querySelectorAll('.disband-max-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const input = e.currentTarget.previousElementSibling;
                input.value = input.max;
                updateDisbandRefund();
            });
        });

        updateTrainingCost();
        updateDisbandRefund();
    }

    // ---------- Armory Purchase Calculator (armory.php) ----------
    const armoryForm = document.getElementById('armory-form');
    if (armoryForm) {
        const summaryItemsEl = document.getElementById('summary-items');
        const grandTotalEl   = document.getElementById('grand-total');
        const quantityInputs = armoryForm.querySelectorAll('.armory-item-quantity');
        const availableCreditsEl = document.querySelector('#armory-summary [data-amount]');
        const availableCredits = toInt(availableCreditsEl?.dataset.amount, 0);

        const updateArmoryCost = () => {
            let grandTotal = 0;
            const frag = document.createDocumentFragment();
            let selectedCount = 0;

            quantityInputs.forEach(input => {
                const quantity = toInt(input.value, 0);
                const itemRow = input.closest('.armory-item');
                const costEl = itemRow?.querySelector('[data-cost]');
                const subtotalEl = input.closest('.flex').querySelector('.subtotal');
                const cost = toInt(costEl?.dataset.cost, 0);
                const subtotal = quantity * cost;

                if (subtotalEl) {
                    subtotalEl.textContent = subtotal.toLocaleString();
                }

                grandTotal += subtotal;

                if (quantity > 0) {
                    selectedCount++;
                    const line = document.createElement('div');
                    line.className = 'flex justify-between';

                    const left = document.createElement('span');
                    left.textContent = `${input.dataset.itemName} x${quantity}`;

                    const right = document.createElement('span');
                    right.className = 'font-semibold';
                    right.textContent = subtotal.toLocaleString();

                    line.append(left, right);
                    frag.appendChild(line);
                }
            });

            grandTotalEl.textContent = grandTotal.toLocaleString();
            if (summaryItemsEl) {
                summaryItemsEl.textContent = '';
                if (selectedCount === 0) {
                    const p = document.createElement('p');
                    p.className = 'text-gray-500 italic';
                    p.textContent = 'Select items to purchase...';
                    summaryItemsEl.appendChild(p);
                } else {
                    summaryItemsEl.appendChild(frag);
                }
            }
        };

        quantityInputs.forEach(input => input.addEventListener('input', updateArmoryCost, { passive: true }));
        
        armoryForm.querySelectorAll('.armory-max-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const clickedInput = e.currentTarget.previousElementSibling;
                const itemRow = clickedInput.closest('.armory-item');
                const costEl = itemRow?.querySelector('[data-cost]');
                const cost = toInt(costEl?.dataset.cost, 0);
                if (cost === 0) return;

                let otherCost = 0;
                quantityInputs.forEach(input => {
                    if (input !== clickedInput) {
                        const otherItemRow = input.closest('.armory-item');
                        const otherCostEl = otherItemRow?.querySelector('[data-cost]');
                        const otherItemCost = toInt(otherCostEl?.dataset.cost, 0);
                        otherCost += toInt(input.value, 0) * otherItemCost;
                    }
                });

                const remainingCredits = Math.max(0, availableCredits - otherCost);
                const maxByCredits = Math.floor(remainingCredits / cost);
                const maxByPrereq = toInt(clickedInput.getAttribute('max'), Infinity);
                
                clickedInput.value = Math.min(maxByCredits, maxByPrereq);
                updateArmoryCost();
            });
        });

        updateArmoryCost(); // Initial call
    }


    // ---------- Advisor Mobile Toggle ----------
    const toggleButton = document.getElementById('toggle-advisor-btn');
    const advisorContainer = document.querySelector('.advisor-container');
    if (toggleButton && advisorContainer) {
        toggleButton.addEventListener('click', () => {
            advisorContainer.classList.toggle('advisor-minimized');
            toggleButton.textContent = advisorContainer.classList.contains('advisor-minimized') ? '+' : '-';
        });
    }

    // ---------- Stats Mobile Toggle ----------
    const statsToggleButton = document.getElementById('toggle-stats-btn');
    const statsContainer = document.querySelector('.stats-container');
    if (statsToggleButton && statsContainer) {
        statsToggleButton.addEventListener('click', () => {
            statsContainer.classList.toggle('stats-minimized');
            statsToggleButton.textContent = statsContainer.classList.contains('stats-minimized') ? '+' : '-';
        });
    }

    // --- Profile Modal Logic (attack.php) ---
    const modal = document.getElementById('profile-modal');
    const modalContent = document.getElementById('profile-modal-content');
    const profileTriggers = document.querySelectorAll('.profile-modal-trigger');

    const openModal = () => modal.classList.remove('hidden');
    const closeModal = () => modal.classList.add('hidden');

    profileTriggers.forEach(trigger => {
        trigger.addEventListener('click', async (e) => {
            e.preventDefault();
            const profileId = trigger.dataset.profileId;
            
            // Show loading state
            modalContent.innerHTML = '<div class="text-center p-8">Loading profile...</div>';
            openModal();

            try {
                const response = await fetch(`/api/get_profile_data.php?id=${profileId}`);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const data = await response.json();

                const html = `
                    <button class="modal-close-btn text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                    <div class="p-6">
                        <div class="flex flex-col md:flex-row items-center gap-4">
                             <img src="${data.avatar_path || 'https://via.placeholder.com/100'}" alt="Avatar" class="w-24 h-24 rounded-full border-2 border-gray-600 object-cover flex-shrink-0">
                             <div class="text-center md:text-left">
                                <h2 class="font-title text-3xl text-white">${data.character_name}</h2>
                                <p class="text-lg text-cyan-300">Level ${data.level} ${data.race} ${data.class}</p>
                             </div>
                        </div>
                        <div class="mt-4 border-t border-gray-700 pt-4 grid grid-cols-2 gap-4 text-sm">
                            <div><span class="font-semibold">Army Size:</span> <span class="text-white">${parseInt(data.army_size).toLocaleString()}</span></div>
                            <div><span class="font-semibold">Workers:</span> <span class="text-white">${parseInt(data.workers).toLocaleString()}</span></div>
                            <div><span class="font-semibold">Soldiers:</span> <span class="text-white">${parseInt(data.soldiers).toLocaleString()}</span></div>
                            <div><span class="font-semibold">Guards:</span> <span class="text-white">${parseInt(data.guards).toLocaleString()}</span></div>
                            <div><span class="font-semibold">Sentries:</span> <span class="text-white">${parseInt(data.sentries).toLocaleString()}</span></div>
                            <div><span class="font-semibold">Spies:</span> <span class="text-white">${parseInt(data.spies).toLocaleString()}</span></div>
                        </div>
                        <div class="mt-4">
                            <h4 class="font-semibold text-cyan-400">Biography</h4>
                            <div class="text-gray-300 italic p-2 bg-gray-900/50 rounded-lg text-sm max-h-24 overflow-y-auto">
                                ${data.biography ? data.biography.replace(/\n/g, '<br>') : 'No biography provided.'}
                            </div>
                        </div>
                    </div>
                `;
                modalContent.innerHTML = html;
                modalContent.querySelector('.modal-close-btn').addEventListener('click', closeModal);

            } catch (error) {
                modalContent.innerHTML = `<div class="text-center p-8 text-red-400">Error loading profile. Please try again.</div>`;
            }
        });
    });

    // Close modal if clicking on the background overlay
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });
});