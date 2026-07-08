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
            // ── Sidebar Toggle ──────────────────────────────────────
            const sidebarToggle = document.getElementById('sidebarToggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', () => {
                    if (window.innerWidth >= 992) {
                        // Desktop: collapse sidebar + persist state
                        document.body.classList.toggle('sidebar-collapsed');
                        localStorage.setItem(
                            'sidebarCollapsed',
                            document.body.classList.contains('sidebar-collapsed')
                        );
                    } else {
                        // Mobile: slide-in overlay
                        document.body.classList.toggle('sidebar-open');
                    }
                });

                // Close mobile sidebar when clicking outside
                document.addEventListener('click', (e) => {
                    const sidebar = document.getElementById('sidebar');
                    if (window.innerWidth < 992 &&
                        document.body.classList.contains('sidebar-open') &&
                        sidebar && !sidebar.contains(e.target) &&
                        e.target !== sidebarToggle && !sidebarToggle.contains(e.target)) {
                        document.body.classList.remove('sidebar-open');
                    }
                });
            }

            // ── Dark Mode Toggle ────────────────────────────────────
            const darkModeToggle = document.getElementById('darkModeToggle');
            if (darkModeToggle) {
                const updateIcon = () => {
                    darkModeToggle.innerHTML = document.body.classList.contains('dark-mode')
                        ? '<i class="fa-regular fa-sun"></i>'
                        : '<i class="fa-solid fa-moon"></i>';
                };

                updateIcon();

                darkModeToggle.addEventListener('click', () => {
                    document.body.classList.toggle('dark-mode');
                    updateIcon();
                    localStorage.setItem(
                        'theme',
                        document.body.classList.contains('dark-mode') ? 'dark' : 'light'
                    );
                });
            }
        });
    </script>
</body>
</html>
