function switchTab(tabId) {
    // Update sidebar items
    document.querySelectorAll('.sidebar-item').forEach(item => {
        item.classList.remove('active');
        if (item.getAttribute('onclick') === `switchTab('${tabId}')`) {
            item.classList.add('active');
        }
    });

    // Update content sections
    document.querySelectorAll('main section').forEach(section => {
        section.classList.remove('active');
    });

    const activeSection = document.getElementById(tabId);
    if (activeSection) {
        activeSection.classList.add('active');
        
        // Scroll to top of content on mobile
        if (window.innerWidth <= 768) {
            window.scrollTo({
                top: document.querySelector('.profile-content').offsetTop - 20,
                behavior: 'smooth'
            });
        }
    }

    // Update URL hash without jumping
    history.replaceState(null, null, `#${tabId}`);
}

document.addEventListener('DOMContentLoaded', () => {
    // Handle initial tab from URL hash
    const hash = window.location.hash.replace('#', '');
    if (hash && document.getElementById(hash)) {
        switchTab(hash);
    }

    // Enhancement for drag & drop zone visual feedback
    const dropZone = document.getElementById('drop-zone');
    if (dropZone) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.add('drag-over'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.remove('drag-over'), false);
        });
    }
});
