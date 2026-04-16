document.addEventListener('DOMContentLoaded', function () {
    const imageToggle = document.getElementById('image-sitemap-toggle');
    const imageOptions = document.getElementById('image-sitemap-options');
    const videoToggle = document.getElementById('video-sitemap-toggle');
    const videoOptions = document.getElementById('video-sitemap-options');

    imageToggle.addEventListener('change', function () {
        imageOptions.style.display = this.checked ? 'block' : 'none';
    });

    videoToggle.addEventListener('change', function () {
        videoOptions.style.display = this.checked ? 'block' : 'none';
    });
});
