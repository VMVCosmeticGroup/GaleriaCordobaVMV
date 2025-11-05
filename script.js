// Configuración de Cloudinary
const CLOUDINARY_CLOUD_NAME = 'galerycordoba';

// Función para construir URL de Cloudinary
function getCloudinaryUrlDirect(imageId, width = 400, height = 400) {
    return `https://res.cloudinary.com/${CLOUDINARY_CLOUD_NAME}/image/upload/w_${width},h_${height},c_fill,q_auto,f_auto/${imageId}`;
}

// Cargar galería
async function loadGallery() {
    try {
        const response = await fetch('./data.json');
        const images = await response.json();

        const galleryWrapper = document.getElementById('gallery_wrapper');

        // Limpiar galería
        galleryWrapper.innerHTML = '';

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
            
            // Click para abrir modal
            img.addEventListener('click', () => {
                openModal(imageLargeUrl, image.titulo);
            });
            
            galleryWrapper.appendChild(img);
        });
    } catch (error) {
        console.error('Error al cargar la galería:', error);
        alert('Error al cargar la galería. Verifica tu archivo data.json y la configuración de Cloudinary');
    }
}

// Funciones del Modal
function openModal(imageSrc, title) {
    const modal = document.getElementById('modal');
    const modalImage = document.getElementById('modal-image');
    
    if (modal && modalImage) {
        modalImage.src = imageSrc;
        modalImage.alt = title;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden'; // Evitar scroll del body
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