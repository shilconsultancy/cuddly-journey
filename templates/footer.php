<?php
// templates/footer.php

// Close the main content area
?>
        </main>
    </div> <!-- Closes the flex container from the header -->

    <!-- JavaScript for interactivity -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const userMenuButton = document.getElementById('user-menu-button');
            const userMenu = document.getElementById('user-menu');
            
            if(userMenuButton) {
                userMenuButton.addEventListener('click', function(event) {
                    event.stopPropagation();
                    if(userMenu) {
                        userMenu.classList.toggle('hidden');
                    }
                });
            }
            
            document.addEventListener('click', function(event) {
                if (userMenu && !userMenu.classList.contains('hidden') && !userMenuButton.contains(event.target)) {
                    userMenu.classList.add('hidden');
                }
            });
        });
    </script>

</body>
</html>
<?php
// Close the database connection at the end of every script that uses it.
if (isset($conn)) {
    $conn->close();
}
?>