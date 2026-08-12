<!-- includes/footer.php -->
        </main> <!-- End Main Content -->

        <!-- Footer -->
        <footer class="footer position-fixed bottom-0 start-0 w-100 bg-white border-top py-2 px-4 d-flex justify-content-between align-items-center" 
                style="height: var(--footer-height); z-index: 1000; padding-left: var(--sidebar-width); transition: padding-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);">
            <div class="small text-muted">&copy; <?php echo date('Y'); ?> Renewly Admin Platform. All rights reserved.</div>
            <div class="d-flex gap-3">
                <a href="#" class="text-decoration-none text-muted small hover-primary">Privacy</a>
                <a href="#" class="text-decoration-none text-muted small hover-primary">Terms</a>
            </div>
        </footer>
    </div> <!-- End Main Wrapper -->

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Init Lucide
        lucide.createIcons();



        // Sidebar Collapse Logic
        const sidebar = document.getElementById('sidebar');
        const collapseToggle = document.getElementById('collapseToggle');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const footer = document.querySelector('.footer');
        const overlay = document.getElementById('sidebarOverlay');

        // Desktop Collapse
        collapseToggle?.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            // Adjust Footer Padding
            if(sidebar.classList.contains('collapsed')) {
                footer.style.paddingLeft = 'var(--sidebar-collapsed-width)';
            } else {
                footer.style.paddingLeft = 'var(--sidebar-width)';
            }
        });

        // Mobile Toggle
        sidebarToggle?.addEventListener('click', () => {
            sidebar.classList.add('show');
            overlay.style.display = 'block';
            setTimeout(() => overlay.style.opacity = '1', 10);
        });

        overlay?.addEventListener('click', () => {
            sidebar.classList.remove('show');
            overlay.style.opacity = '0';
            setTimeout(() => overlay.style.display = 'none', 300);
        });
        
    </script>
    <style>
        /* Footer specific adjustment for collapse state */
        .sidebar.collapsed ~ .main-content ~ .footer {
            padding-left: var(--sidebar-collapsed-width) !important;
        }
        @media (max-width: 1024px) {
            .footer { padding-left: 0 !important; }
        }
        .hover-primary:hover { color: var(--primary-color) !important; }
    </style>
</body>
</html>
