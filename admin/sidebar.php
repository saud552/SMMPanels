    </div>
</div>

<div class="toast" id="toast"></div>

<script>
    // القائمة الجانبية المنزلقة للموبايل
    const menuBtn = document.getElementById('menuBtn');
    const sideMenu = document.getElementById('sideMenu');
    const menuOverlay = document.getElementById('menuOverlay');
    const closeMenuBtn = document.getElementById('closeMenuBtn');

    function openMenu() {
        sideMenu.classList.add('open');
        menuOverlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeMenu() {
        sideMenu.classList.remove('open');
        menuOverlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    if (menuBtn) menuBtn.addEventListener('click', openMenu);
    if (closeMenuBtn) closeMenuBtn.addEventListener('click', closeMenu);
    if (menuOverlay) menuOverlay.addEventListener('click', closeMenu);

    // إغلاق القائمة عند الضغط على زر Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sideMenu.classList.contains('open')) {
            closeMenu();
        }
    });

    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = 'toast show';
        toast.style.background = type === 'error' ? '#ef4444' : '#10b981';
        setTimeout(() => { toast.classList.remove('show'); }, 3000);
    }
</script>
</body>
</html>