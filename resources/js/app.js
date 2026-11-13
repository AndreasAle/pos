import './bootstrap';

const productExperience = document.querySelector('[data-yoka-product-experience]');

if (productExperience) {
    const loadExperience = () => import('./product-experience/YokaProductExperience.jsx')
        .then(({ mountYokaProductExperience }) => mountYokaProductExperience(productExperience));

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            if (!entries[0].isIntersecting) return;
            observer.disconnect();
            loadExperience();
        }, { rootMargin: '500px 0px' });
        observer.observe(productExperience);
    } else {
        loadExperience();
    }
}
