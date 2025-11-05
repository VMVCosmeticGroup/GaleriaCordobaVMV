// Configuración de Cloudinary
const CLOUDINARY_CLOUD_NAME = 'galerycordoba';

// Array de mensajes inspiradores para el spinner
const LOADING_MESSAGES = [
    'Buscando momentos...',
    'Cargando recuerdos...',
    'Capturando emociones...',
    'Desempolvando recuerdos...',
    'Recopilando historias...',
    'Evocando sensaciones...',
    'Guardando instantes...',
    'Reuniendo momentos felices...',
    'Descubriendo aventuras...',
    'Recuperando vivencias...'
];

// Variables globales para modal navigation
let allImages = [];
let currentImageIndex = 0;
let lastDirection = 'right'; // 'right' o 'left'

// Función para construir URL de Cloudinary
function getCloudinaryUrlDirect(imageId, width = 400, height = 400) {
    return `https://res.cloudinary.com/${CLOUDINARY_CLOUD_NAME}/image/upload/w_${width},h_${height},c_fill,q_auto,f_auto/${imageId}`;
}

// Función para obtener un mensaje aleatorio del array
function getRandomLoadingMessage() {
    return LOADING_MESSAGES[Math.floor(Math.random() * LOADING_MESSAGES.length)];
}

// Función para actualizar el mensaje del spinner de forma dinámica
function startSpinnerMessageRotation() {
    const spinnerText = document.querySelector('.spinner-text');
    if (!spinnerText) return;

    // Actualizar mensaje cada 1.2 segundos
    const interval = setInterval(() => {
        spinnerText.style.opacity = '0.3';
        setTimeout(() => {
            spinnerText.textContent = getRandomLoadingMessage();
            spinnerText.style.opacity = '0.8';
            spinnerText.style.transition = 'opacity 0.3s ease';
        }, 150);
    }, 1200);

    return interval;
}

// Cargar galería con precarga en caché
async function loadGallery() {
    const spinner = document.getElementById('gallery-spinner');
    const spinnerText = document.querySelector('.spinner-text');
    
    // Mostrar primer mensaje aleatorio
    if (spinnerText) {
        spinnerText.textContent = getRandomLoadingMessage();
    }
    
    // Iniciar rotación de mensajes
    const messageInterval = startSpinnerMessageRotation();
    
    try {
        const response = await fetch('./data.json');
        const images = await response.json();
        allImages = images; // Guardar lista global

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

        // Esperar 2.5 segundos adicionales para asegurar que todo esté en caché
        await new Promise(resolve => setTimeout(resolve, 2800));

        // Detener rotación de mensajes
        clearInterval(messageInterval);

        // Crear elementos de galería
        images.forEach((image, index) => {
            const imageUrl = getCloudinaryUrlDirect(image.id, 400, 400);

            const img = document.createElement('img');
            img.src = imageUrl;
            img.alt = image.alt;
            img.loading = 'lazy';
            img.title = image.titulo;
            img.style.cursor = 'pointer';
            img.addEventListener('click', () => {
                currentImageIndex = index; // Guardar índice actual
                openModal(image.id, image.titulo, true); // skipTransition = true en primera apertura
            });
            galleryWrapper.appendChild(img);
        });

        // Inicializar scroll dots
        initScrollDots(images.length);

        // Ocultar spinner con animación suave
        if (spinner) {
            spinner.classList.add('hidden');
        }
    } catch (error) {
        console.error('Error al cargar la galería:', error);
        alert('Error al cargar la galería. Verifica tu archivo data.json y la configuración de Cloudinary');
        
        // Detener rotación de mensajes en caso de error
        clearInterval(messageInterval);
        
        // Ocultar spinner incluso si hay error
        if (spinner) {
            spinner.classList.add('hidden');
        }
    }
}

// Funciones del Modal
function openModal(imageId, title, skipTransition = false) {
    const modal = document.getElementById('modal');
    const modalImage = document.getElementById('modal-image');
    const downloadBtn = document.getElementById('download-btn');
    const whatsappBtn = document.getElementById('whatsapp-btn');
    const facebookBtn = document.getElementById('facebook-btn');
    const instagramBtn = document.getElementById('instagram-btn');
    
    if (modal && modalImage) {
        // Añadir transición si no se está abriendo por primera vez
        if (!skipTransition && modal.classList.contains('active')) {
            // Limpiar todas las clases de animación primero
            modalImage.classList.remove('transitioning-zoom-in', 'transitioning-out-right', 'transitioning-out-left', 'transitioning-in-left', 'transitioning-in-right');
            
            // Forzar reflow para que CSS reconozca el cambio
            void modalImage.offsetWidth;
            
            // Aplicar animación de salida basada en la dirección
            if (lastDirection === 'right') {
                modalImage.classList.add('transitioning-out-right');
            } else {
                modalImage.classList.add('transitioning-out-left');
            }
            
            setTimeout(() => {
                loadImageContent();
            }, 200);
        } else {
            loadImageContent();
        }
        
        function loadImageContent() {
            // Limpiar todas las clases de animación
            modalImage.classList.remove('transitioning-zoom-in', 'transitioning-out-right', 'transitioning-out-left', 'transitioning-in-left', 'transitioning-in-right');
            
            // Forzar reflow para que CSS reconozca el cambio
            void modalImage.offsetWidth;
            
            // Mostrar primero la imagen en baja calidad
            const lowResUrl = getCloudinaryUrlDirect(imageId, 200, 200);
            const highResUrl = getCloudinaryUrlDirect(imageId, 1200, 1200);
            modalImage.src = lowResUrl;
            modalImage.alt = title;
            modalImage.style.filter = 'blur(10px) grayscale(0.5)';
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Aplicar animación correcta basada en si es primera apertura o transición
            if (skipTransition) {
                // Primera apertura: zoomIn
                modalImage.classList.add('transitioning-zoom-in');
            } else {
                // Transición entre imágenes: slide
                if (lastDirection === 'right') {
                    modalImage.classList.add('transitioning-in-left');
                } else {
                    modalImage.classList.add('transitioning-in-right');
                }
            }
            
            // Precargar la imagen en alta calidad
            const imgHigh = new window.Image();
            imgHigh.src = highResUrl;
            imgHigh.onload = () => {
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
                const text = encodeURIComponent('¡Mira esta foto del viaje Salerm Cádiz! ' + highResUrl);
                whatsappBtn.href = `https://wa.me/?text=${text}`;
            }
            if (facebookBtn) {
                facebookBtn.href = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(highResUrl)}`;
            }
            if (instagramBtn) {
                instagramBtn.href = 'https://www.instagram.com/';
            }
        }
    }
}

function showNextImage() {
    if (allImages.length > 0) {
        lastDirection = 'right'; // Hacia adelante = sale por la derecha, entra por la izquierda
        currentImageIndex = (currentImageIndex + 1) % allImages.length;
        const image = allImages[currentImageIndex];
        openModal(image.id, image.titulo);
    }
}

function showPrevImage() {
    if (allImages.length > 0) {
        lastDirection = 'left'; // Hacia atrás = sale por la izquierda, entra por la derecha
        currentImageIndex = (currentImageIndex - 1 + allImages.length) % allImages.length;
        const image = allImages[currentImageIndex];
        openModal(image.id, image.titulo);
    }
}

function closeModal() {
    const modal = document.getElementById('modal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto'; // Restaurar scroll
    }
}

// Scroll Dots Handler
let scrollDotsCount = 0;

function initScrollDots(totalImages) {
    // Calcular número de filas (16 columnas con 2 span = 8 imágenes por fila)
    const imagesPerRow = 8;
    const rows = Math.ceil(totalImages / imagesPerRow);
    // Mostrar solo un tercio de los puntos
    const visibleDots = Math.max(1, Math.ceil(rows / 3));
    scrollDotsCount = rows;

    const scrollDotsContainer = document.getElementById('scroll-dots');
    scrollDotsContainer.innerHTML = '';

    for (let i = 0; i < visibleDots; i++) {
        const dot = document.createElement('div');
        dot.className = 'scroll-dot';
        if (i === 0) dot.classList.add('active');
        dot.addEventListener('click', () => {
            const container = document.getElementById('gallery-container');
            const scrollHeight = (container.scrollHeight - container.clientHeight) / (visibleDots - 1 || 1);
            container.scrollTo({
                top: scrollHeight * i,
                behavior: 'smooth'
            });
        });
        scrollDotsContainer.appendChild(dot);
    }

    // Scroll snap: salta al siguiente punto según dirección
    const container = document.getElementById('gallery-container');
    let isSnapping = false;
    const snapPositions = [];
    for (let i = 0; i < visibleDots; i++) {
        snapPositions.push((container.scrollHeight - container.clientHeight) * (i / (visibleDots - 1 || 1)));
    }

    let currentIndex = 0;
    // Margen de detección bajo
    const DETECTION_MARGIN = 0.5;

    container.addEventListener('wheel', (e) => {
        if (isSnapping) return;
        isSnapping = true;
        if (e.deltaY > DETECTION_MARGIN) {
            // Scroll hacia abajo
            currentIndex = Math.min(currentIndex + 1, snapPositions.length - 1);
        } else if (e.deltaY < -DETECTION_MARGIN) {
            // Scroll hacia arriba
            currentIndex = Math.max(currentIndex - 1, 0);
        }
        container.scrollTo({
            top: snapPositions[currentIndex],
            behavior: 'smooth'
        });
        // Actualizar dots activos
        document.querySelectorAll('.scroll-dot').forEach((dot, index) => {
            dot.classList.toggle('active', index === currentIndex);
        });
        setTimeout(() => { isSnapping = false; }, 350);
    });
}

// Event listeners del modal - Se ejecutan cuando el DOM está listo
document.addEventListener('DOMContentLoaded', () => {
    // Cerrar modal al hacer click en la X
    const closeBtn = document.querySelector('.modal-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }

    // Botones de navegación
    const prevBtn = document.getElementById('modal-prev');
    const nextBtn = document.getElementById('modal-next');
    if (prevBtn) {
        prevBtn.addEventListener('click', showPrevImage);
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', showNextImage);
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
        // Navegar con teclas de flecha
        if (e.key === 'ArrowLeft') {
            showPrevImage();
        }
        if (e.key === 'ArrowRight') {
            showNextImage();
        }
    });

    // Cargar galería
    loadGallery();
});