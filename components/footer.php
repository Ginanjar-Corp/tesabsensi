<?php
// project-root/components/footer.php
?>
    </div><!-- Tutup div .flex-1 atau div konten utama lainnya jika ada -->
</div><!-- Tutup div .flex (Kontainer Utama Sidebar & Konten) jika ada -->

<script>
    // Fungsi untuk mengaktifkan/menonaktifkan menu mobile (untuk header.php)
    function toggleMobileMenu() {
        const mobileMenu = document.getElementById('mobile-menu');
        const overlay = document.getElementById('mobile-menu-overlay');
        
        if (mobileMenu && overlay) {
            mobileMenu.classList.toggle('translate-x-full'); // Menggeser menu masuk/keluar
            overlay.classList.toggle('active'); // Mengaktifkan/menonaktifkan overlay
        }
    }

    // Event listener untuk tombol hamburger di header.php (navbar utama)
    const mainHamburgerButton = document.getElementById('hamburger-button'); // ID dari header.php
    if (mainHamburgerButton) {
        mainHamburgerButton.addEventListener('click', toggleMobileMenu);
    }

    // Opsional: Tutup menu mobile jika ukuran layar berubah dari mobile ke desktop
    window.addEventListener('resize', () => {
        const mobileMenu = document.getElementById('mobile-menu');
        const overlay = document.getElementById('mobile-menu-overlay');
        if (mobileMenu && overlay) {
            // Jika lebar layar lebih besar dari md breakpoint (768px default Tailwind)
            if (window.innerWidth >= 768) {
                mobileMenu.classList.remove('translate-x-full'); // Pastikan menu terlihat di desktop
                overlay.classList.remove('active'); // Pastikan overlay tersembunyi
            }
        }
    });

    // --- JavaScript untuk sidebar admin/guru ---
    // Pastikan fungsi toggleSidebar ini hanya dipanggil jika elemen sidebar ada
    function toggleSidebar() {
        const sidebar = document.getElementById('guru-sidebar') || document.getElementById('admin-sidebar');
        const overlaySidebar = document.getElementById('sidebar-overlay'); // Overlay khusus sidebar

        if (sidebar && overlaySidebar) {
            sidebar.classList.toggle('-translate-x-full');
            overlaySidebar.classList.toggle('active');
        }
    }

    // Event listener untuk tombol hamburger sidebar (dari header_admin.php atau header_guru.php)
    const adminHamburgerButton = document.getElementById('admin-hamburger-button'); // ID dari header_admin.php
    const guruHamburgerButton = document.getElementById('guru-hamburger-button');   // ID dari header_guru.php

    if (adminHamburgerButton) {
        adminHamburgerButton.addEventListener('click', toggleSidebar);
    } else if (guruHamburgerButton) {
        guruHamburgerButton.addEventListener('click', toggleSidebar);
    }

    // Optional: Tutup sidebar saat mengklik item menu di mobile
    const sidebarMenuItems = document.querySelectorAll('.sidebar-menu-item');
    sidebarMenuItems.forEach(item => {
        item.addEventListener('click', () => {
            const sidebar = document.getElementById('guru-sidebar') || document.getElementById('admin-sidebar');
            if (sidebar && !sidebar.classList.contains('-translate-x-full') && window.innerWidth < 768) {
                toggleSidebar();
            }
        });
    });

    // Optional: Tutup sidebar jika ukuran layar berubah dari mobile ke desktop (untuk sidebar)
    window.addEventListener('resize', () => {
        const sidebar = document.getElementById('guru-sidebar') || document.getElementById('admin-sidebar');
        const overlaySidebar = document.getElementById('sidebar-overlay');
        if (sidebar && overlaySidebar) {
            if (window.innerWidth >= 768) {
                sidebar.classList.remove('-translate-x-full');
                overlaySidebar.classList.remove('active');
            }
        }
    });
</script>
</body>
</html>
