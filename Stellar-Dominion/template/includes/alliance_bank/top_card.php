<!-- /template/includes/alliance_bank/top_card.php -->

<div class="flex justify-between items-center">
            <div>
                <h2 class="font-title text-2xl text-cyan-400">Alliance Bank</h2>
                <p class="text-lg">Current Funds:
                    <span class="font-bold text-yellow-300">
                        <?php echo number_format((int)($alliance['bank_credits'] ?? 0)); ?> Credits
                    </span>
                </p>
                <p class="text-xs opacity-80 mt-1">
                    Alliance bank accrues <span class="font-semibold text-green-300">2% interest</span> every hour. Deposits hourly.
                </p>
            </div>
            <a href="/alliance_transfer.php" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-6 rounded-lg">Member Transfers</a>
        </div>
        <!-- -->
        <div class="border-b border-gray-600 mt-4">
            <nav class="flex space-x-4">
                <a href="?tab=main" class="py-2 px-4 <?php echo $current_tab === 'main' ? 'text-white border-b-2 border-cyan-400' : 'text-gray-400 hover:text-white'; ?>">Donate & Withdraw</a>
                <a href="?tab=loans" class="py-2 px-4 <?php echo $current_tab === 'loans' ? 'text-white border-b-2 border-cyan-400' : 'text-gray-400 hover:text-white'; ?>">Loans</a>
                <a href="?tab=ledger" class="py-2 px-4 <?php echo $current_tab === 'ledger' ? 'text-white border-b-2 border-cyan-400' : 'text-gray-400 hover:text-white'; ?>">Ledger & Stats</a>
            </nav>
        </div>