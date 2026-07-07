            </div> <!-- End Page Content (.app-content) -->
            
            <footer class="mt-auto py-3 text-center border-top" style="background-color: var(--bg-surface); color: var(--text-secondary); font-size: 0.85rem; border-color: var(--border-color) !important;">
                <div class="container-fluid">
                    &copy; <?php echo date("Y"); ?> AutoRental. All Rights Reserved.
                </div>
            </footer>
        </main> <!-- End Main Content Wrapper (.app-main) -->
    </div> <!-- End Main Layout Wrapper (.app-wrapper) -->

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- App Custom JS -->
    <script src="/car-rental/assets/js/main.js"></script>
    
    <!-- Custom Layout JS -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // Sidebar Toggle (Mobile & Desktop)
            const sidebarToggle = document.getElementById('sidebarToggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', () => {
                    // For mobile
                    document.body.classList.toggle('sidebar-open');
                    // For desktop
                    document.body.classList.toggle('sidebar-collapsed');
                });
            }

            // Dark Mode Toggle
            const darkModeToggle = document.getElementById('darkModeToggle');
            if (darkModeToggle) {
                const updateIcon = () => {
                    if (document.body.classList.contains('dark-mode')) {
                        darkModeToggle.innerHTML = '<i class="fa-regular fa-sun"></i>';
                    } else {
                        darkModeToggle.innerHTML = '<i class="fa-solid fa-moon"></i>';
                    }
                };
                
                updateIcon();

                darkModeToggle.addEventListener('click', () => {
                    document.body.classList.toggle('dark-mode');
                    updateIcon();
                    
                    if (document.body.classList.contains('dark-mode')) {
                        localStorage.setItem('theme', 'dark');
                    } else {
                        localStorage.setItem('theme', 'light');
                    }
                });
            }
        });
    </script>
</body>
</html>
