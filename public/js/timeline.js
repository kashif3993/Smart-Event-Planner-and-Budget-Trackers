document.addEventListener('DOMContentLoaded', function () {
    const exportBtn = document.getElementById('exportTimelineBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            window.print();
        });
    }
});
