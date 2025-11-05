// Configuración de Cloudinary
const CLOUDINARY_CLOUD_NAME = 'galerycordoba';
const CLOUDINARY_TRANSFORMATIONS = 'w_400,h_400,c_fill,q_auto,f_auto';

// Función para construir URL de Cloudinary
function getCloudinaryUrl(imageId, width = 400, height = 400) {
    return `https://res.cloudinary.com/${CLOUDINARY_CLOUD_NAME}/image/fetch/w_${width},h_${height},c_fill,q_auto,f_auto/https://res.cloudinary.com/${CLOUDINARY_CLOUD_NAME}/image/upload/${imageId}`;
}

// Función alternativa usando el método directo
function getCloudinaryUrlDirect(imageId, width = 400, height = 400) {
    return `https://res.cloudinary.com/${CLOUDINARY_CLOUD_NAME}/image/upload/w_${width},h_${height},c_fill,q_auto,f_auto/${imageId}`;
}

// Cargar galería
async function loadGallery() {
    try {
        const response = await fetch('./data.json');
        const images = await response.json();

        const galleryContainer = document.getElementById('gallery');
        const loadingDiv = document.getElementById('loading');

        // Limpiar galería
        galleryContainer.innerHTML = '';

        // Crear elementos de galería
        images.forEach((image, index) => {
            const imageUrl = getCloudinaryUrlDirect(image.id);
            const imageLargeUrl = getCloudinaryUrlDirect(image.id, 800, 800);

            const galleryItem = document.createElement('div');
            galleryItem.className = 'gallery-item';
            galleryItem.innerHTML = `
                <img src="${imageUrl}" alt="${image.alt}" loading="lazy">
                <div class="gallery-item-overlay">
                    <div class="gallery-item-title">${image.titulo}</div>
                    <div class="gallery-item-alt">${image.alt}</div>
                </div>
            `;

            galleryItem.addEventListener('click', () => openModal(imageLargeUrl, image.titulo));
            galleryContainer.appendChild(galleryItem);
        });

        // Ocultar loading
        loadingDiv.style.display = 'none';
    } catch (error) {
        console.error('Error al cargar la galería:', error);
        document.getElementById('loading').innerHTML = `
            <p style="color: #ff6b6b;">Error al cargar la galería</p>
            <p style="font-size: 0.9rem; opacity: 0.8;">Verifica tu archivo data.json y la configuración de Cloudinary</p>
        `;
    }
}

// Funciones del Modal
function openModal(imageSrc, title) {
    const modal = document.getElementById('modal');
    const modalImage = document.getElementById('modal-image');
    modalImage.src = imageSrc;
    modalImage.alt = title;
    modal.classList.add('active');
}

function closeModal() {
    const modal = document.getElementById('modal');
    modal.classList.remove('active');
}

// Event listeners del modal
document.getElementById('modal').addEventListener('click', (e) => {
    if (e.target.id === 'modal') {
        closeModal();
    }
});

document.querySelector('.modal-close').addEventListener('click', closeModal);

// Cerrar modal con tecla ESC
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeModal();
    }
});

// Cargar galería cuando se carga el DOM
document.addEventListener('DOMContentLoaded', loadGallery);