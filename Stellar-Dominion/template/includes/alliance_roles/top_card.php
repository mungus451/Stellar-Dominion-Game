<!-- /template/includes/alliance_roles/top_card.php -->
<div>
                <h1 class="font-title text-3xl text-cyan-400 border-b border-gray-600 pb-2 mb-2">Alliance Command & Hierarchy</h1>
                <p class="text-gray-400">Manage member roles, create new ranks, and define the permissions that govern your alliance.</p>
            </div>

            <?php if(isset($_SESSION['alliance_roles_message'])): ?>
                <div class="bg-cyan-900 border border-cyan-500/50 text-cyan-300 p-3 rounded-md text-center">
                    <?php echo htmlspecialchars($_SESSION['alliance_roles_message']); unset($_SESSION['alliance_roles_message']); ?>
                </div>
            <?php endif; ?>
            <?php if(isset($_SESSION['alliance_error']) || isset($_SESSION['alliance_roles_error'])): ?>
                <div class="bg-red-900 border border-red-500/50 text-red-300 p-3 rounded-md text-center">
                    <?php 
                        echo htmlspecialchars($_SESSION['alliance_error'] ?? $_SESSION['alliance_roles_error']); 
                        unset($_SESSION['alliance_error'], $_SESSION['alliance_roles_error']); 
                    ?>
                </div>
            <?php endif; ?>