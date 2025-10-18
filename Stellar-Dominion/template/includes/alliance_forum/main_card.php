<!-- /template/includes/alliance_forum/main_card.php -->

<div class="content-box rounded-lg p-6">
             <div class="flex justify-between items-center mb-4">
                <h1 class="font-title text-3xl text-cyan-400">Alliance Forum</h1>
                <a href="create_thread.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg">Create New Thread</a>
             </div>

             <div class="overflow-x-auto">
                 <table class="w-full text-sm text-left">
                     <thead class="bg-gray-800">
                         <tr>
                             <th class="p-2">Thread / Author</th>
                             <th class="p-2 text-center">Replies</th>
                             <th class="p-2">Last Post</th>
                         </tr>
                     </thead>
                     <tbody>
                     <?php while($thread = mysqli_fetch_assoc($threads_result)): ?>
                         <tr class="border-t border-gray-700">
                             <td class="p-2">
                                 <a href="view_thread.php?id=<?php echo $thread['id']; ?>" class="font-bold text-white hover:underline">
                                     <?php echo htmlspecialchars($thread['title']); ?>
                                 </a>
                                 <p class="text-xs">by <?php echo htmlspecialchars($thread['author_name']); ?></p>
                             </td>
                             <td class="p-2 text-center"><?php echo $thread['post_count']; ?></td>
                             <td class="p-2"><?php echo $thread['last_post_at']; ?></td>
                         </tr>
                     <?php endwhile; ?>
                     </tbody>
                 </table>
             </div>
         </div>