// Configuración de Cloudinary
const CLOUDINARY_CLOUD_NAME = 'galerycordoba';

// Función para construir URL de Cloudinary
function getCloudinaryUrlDirect(imageId, width = 400, height = 400) {
    return `https://res.cloudinary.com/${CLOUDINARY_CLOUD_NAME}/image/upload/w_${width},h_${height},c_fill,q_auto,f_auto/${imageId}`;
}

// Cargar galería con precarga en caché
async function loadGallery() {
    try {
        const response = await fetch('./data.json');
        const images = await response.json();

        const galleryWrapper = document.getElementById('gallery_wrapper');
        galleryWrapper.innerHTML = '';

        // Precargar todas las imágenes en caché
        const preloadPromises = images.map(image => {
            return new Promise(resolve => {
                const imageUrl = getCloudinaryUrlDirect(image.id, 400, 400);
                const imgPreload = new window.Image();
                imgPreload.src = imageUrl;
                imgPreload.onload = () => resolve();
                imgPreload.onerror = () => resolve();
            });
        });
        await Promise.all(preloadPromises);

        // Precargar todas las imágenes grandes en caché (para el modal)
        const preloadLargePromises = images.map(image => {
            return new Promise(resolve => {
                const imageLargeUrl = getCloudinaryUrlDirect(image.id, 1200, 1200);
                const imgLargePreload = new window.Image();
                imgLargePreload.src = imageLargeUrl;
                imgLargePreload.onload = () => resolve();
                imgLargePreload.onerror = () => resolve();
            });
        });
        await Promise.all(preloadLargePromises);

        // Crear elementos de galería
        images.forEach((image, index) => {
            const imageUrl = getCloudinaryUrlDirect(image.id, 400, 400);
            const imageLargeUrl = getCloudinaryUrlDirect(image.id, 1200, 1200);

            const img = document.createElement('img');
            img.src = imageUrl;
            img.alt = image.alt;
            img.loading = 'lazy';
            img.title = image.titulo;
            img.style.cursor = 'pointer';
            img.addEventListener('click', () => {
                openModal(image.id, image.titulo);
            });
            galleryWrapper.appendChild(img);
        });
    } catch (error) {
        console.error('Error al cargar la galería:', error);
        alert('Error al cargar la galería. Verifica tu archivo data.json y la configuración de Cloudinary');
    }
}

// Funciones del Modal
function openModal(imageId, title) {
    const modal = document.getElementById('modal');
    const modalImage = document.getElementById('modal-image');
    const downloadBtn = document.getElementById('download-btn');
    const whatsappBtn = document.getElementById('whatsapp-btn');
    const facebookBtn = document.getElementById('facebook-btn');
    const instagramBtn = document.getElementById('instagram-btn');
    if (modal && modalImage) {
        // Mostrar primero la imagen en baja calidad
        const lowResUrl = getCloudinaryUrlDirect(imageId, 200, 200);
        const highResUrl = getCloudinaryUrlDirect(imageId, 1200, 1200);
        modalImage.src = lowResUrl;
        modalImage.alt = title;
        modalImage.style.filter = 'blur(10px) grayscale(0.5)';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden'; // Evitar scroll del body
        // Precargar la imagen en alta calidad
        const imgHigh = new window.Image();
        imgHigh.src = highResUrl;
        imgHigh.onload = () => {
            // Transición suave
            modalImage.src = highResUrl;
            modalImage.style.transition = 'filter 0.5s';
            modalImage.style.filter = 'none';
        };
        // Actualizar enlaces de acciones
        if (downloadBtn) {
            downloadBtn.href = highResUrl;
            downloadBtn.setAttribute('download', title.replace(/\s+/g, '_') + '.jpg');
        }
        if (whatsappBtn) {
            const text = encodeURIComponent('¡Mira esta foto de mi viaje a Cádiz con VMV! ' + highResUrl);
            whatsappBtn.href = `https://wa.me/?text=${text}`;
        }
        if (facebookBtn) {
            facebookBtn.href = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(highResUrl)}`;
        }
        if (instagramBtn) {
            instagramBtn.href = 'https://www.instagram.com/'; // Instagram no permite compartir directo por URL
        }
    }
}

function closeModal() {
    const modal = document.getElementById('modal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto'; // Restaurar scroll
    }
}

// Event listeners del modal - Se ejecutan cuando el DOM está listo
document.addEventListener('DOMContentLoaded', () => {
    // Cerrar modal al hacer click en la X
    const closeBtn = document.querySelector('.modal-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }

    // Cerrar modal al hacer click en el área de fondo
    const modal = document.getElementById('modal');
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal();
            }
        });
    }

    // Cerrar modal con tecla ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeModal();
        }
    });

    // Cargar galería
    loadGallery();
});