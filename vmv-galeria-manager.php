<?php
/**
 * Plugin Name: VMV Galery Manager
 * Description: Gestor de galerías interactivas con Cloudinary - Crear, importar y gestionar múltiples galerías
 * Version: 2.0.0
 * Author: Alejandro Cabrera Carrascp | VMV Cosmetic Group
 * License: GPL-2.0+
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Crear tabla al activar el plugin
register_activation_hook(__FILE__, 'vmv_galeria_install');

function vmv_galeria_install() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'vmv_galleries';

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        gallery_name varchar(255) NOT NULL,
        gallery_slug varchar(191) UNIQUE NOT NULL,
        gallery_data longtext NOT NULL,
        cloudinary_cloud varchar(100) DEFAULT 'galerycordoba',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY slug (gallery_slug)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Hook para crear la tabla en cada sitio al activar en multisite
function vmv_galeria_multisite_activation($network_wide) {
    global $wpdb;
    if (function_exists('is_multisite') && is_multisite() && $network_wide) {
        // Para cada blog de la red
        $blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
        foreach ($blog_ids as $blog_id) {
            switch_to_blog($blog_id);
            vmv_galeria_install();
            restore_current_blog();
        }
    } else {
        vmv_galeria_install();
    }
}
register_activation_hook(__FILE__, 'vmv_galeria_multisite_activation');

// Menú admin
add_action('admin_menu', 'vmv_galeria_admin_menu');

function vmv_galeria_admin_menu() {
    add_menu_page(
        'VMV Galerías',
        'VMV Galerías',
        'manage_options',
        'vmv-galeria-manager',
        'vmv_galeria_admin_page',
        'dashicons-images-alt2',
        25
    );
    
    add_submenu_page(
        'vmv-galeria-manager',
        'Crear Galería',
        'Crear Galería',
        'manage_options',
        'vmv-galeria-create',
        'vmv_galeria_create_page'
    );
    
    add_submenu_page(
        'vmv-galeria-manager',
        'Ajustes',
        'Ajustes',
        'manage_options',
        'vmv-galeria-settings',
        'vmv_galeria_settings_page'
    );
}

// Página principal - Listar galerías
function vmv_galeria_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vmv_galleries';
    
    // Manejar eliminación
    if (isset($_POST['delete_gallery']) && wp_verify_nonce($_POST['_wpnonce'], 'vmv_delete_gallery')) {
        $gallery_id = intval($_POST['gallery_id']);
        $wpdb->delete($table_name, ['id' => $gallery_id]);
        echo '<div class="notice notice-success"><p>Galería eliminada correctamente.</p></div>';
    }
    
    $galleries = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
    
    ?>
    <div class="wrap">
        <h1>VMV Galerías <a href="?page=vmv-galeria-create" class="page-title-action">Crear Nueva</a></h1>
        
        <?php if (empty($galleries)): ?>
            <p>No hay galerías creadas aún. <a href="?page=vmv-galeria-create">Crea una nueva</a></p>
        <?php else: ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Shortcode</th>
                        <th>Imágenes</th>
                        <th>Cloudinary</th>
                        <th>Creada</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($galleries as $gallery): 
                        $data = json_decode($gallery->gallery_data, true);
                        $image_count = is_array($data) ? count($data) : 0;
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($gallery->gallery_name); ?></strong></td>
                        <td><code>[vmv_galeria slug="<?php echo esc_attr($gallery->gallery_slug); ?>"]</code></td>
                        <td><?php echo $image_count; ?> imágenes</td>
                        <td><?php echo esc_html($gallery->cloudinary_cloud); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($gallery->created_at)); ?></td>
                        <td>
                            <a href="?page=vmv-galeria-create&edit=<?php echo $gallery->id; ?>" class="button button-small">Editar</a>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field('vmv_delete_gallery'); ?>
                                <input type="hidden" name="gallery_id" value="<?php echo $gallery->id; ?>">
                                <button type="submit" name="delete_gallery" class="button button-small button-link-delete" onclick="return confirm('¿Eliminar esta galería?');">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

// Página crear/editar galería
function vmv_galeria_create_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vmv_galleries';
    // Si la tabla no existe, crearla
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            gallery_name varchar(255) NOT NULL,
            gallery_slug varchar(191) UNIQUE NOT NULL,
            gallery_data longtext NOT NULL,
            cloudinary_cloud varchar(100) DEFAULT 'galerycordoba',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (gallery_slug)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    $edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : null;
    $gallery = $edit_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $edit_id)) : null;
    
    // Manejar guardado
    if (isset($_POST['save_gallery']) && wp_verify_nonce($_POST['_wpnonce'], 'vmv_save_gallery')) {
        $name = sanitize_text_field($_POST['gallery_name']);
        $slug = sanitize_title($_POST['gallery_slug']);
        $cloudinary = sanitize_text_field($_POST['cloudinary_cloud']) ?: 'galerycordoba';
        
        // Validar slug único
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE gallery_slug = %s" . ($edit_id ? " AND id != $edit_id" : ""),
            $slug
        ));
        
        if ($existing) {
            echo '<div class="notice notice-error"><p>El slug ya existe. Usa otro.</p></div>';
        } else {
            // Procesar JSON
            $gallery_data = [];
            
            if (isset($_FILES['json_file']) && $_FILES['json_file']['size'] > 0) {
                $file_content = file_get_contents($_FILES['json_file']['tmp_name']);
                $json_data = json_decode($file_content, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    echo '<div class="notice notice-error"><p>JSON inválido: ' . json_last_error_msg() . '</p></div>';
                    $json_data = null;
                }
                $gallery_data = $json_data ?: [];
            } elseif ($edit_id && $gallery) {
                $gallery_data = json_decode($gallery->gallery_data, true) ?: [];
            }
            
            if (!empty($gallery_data)) {
                $data_json = json_encode($gallery_data);
                
                if ($edit_id) {
                    $wpdb->update(
                        $table_name,
                        [
                            'gallery_name' => $name,
                            'gallery_slug' => $slug,
                            'gallery_data' => $data_json,
                            'cloudinary_cloud' => $cloudinary
                        ],
                        ['id' => $edit_id]
                    );
                    echo '<div class="notice notice-success"><p>Galería actualizada correctamente.</p></div>';
                } else {
                    $wpdb->insert(
                        $table_name,
                        [
                            'gallery_name' => $name,
                            'gallery_slug' => $slug,
                            'gallery_data' => $data_json,
                            'cloudinary_cloud' => $cloudinary
                        ]
                    );
                    echo '<div class="notice notice-success"><p>Galería creada correctamente. Shortcode: <code>[vmv_galeria slug="' . esc_html($slug) . '"]</code></p></div>';
                }
                
                // Limpiar variables para formulario vacío
                $gallery = null;
                $edit_id = null;
            }
        }
    }
    
    $gallery_name = $gallery ? $gallery->gallery_name : '';
    $gallery_slug = $gallery ? $gallery->gallery_slug : '';
    $cloudinary_cloud = $gallery ? $gallery->cloudinary_cloud : 'galerycordoba';
    $image_count = $gallery ? count(json_decode($gallery->gallery_data, true)) : 0;
    
    ?>
    <div class="wrap">
        <h1><?php echo $edit_id ? 'Editar Galería' : 'Crear Nueva Galería'; ?></h1>
        
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('vmv_save_gallery'); ?>
            
            <table class="form-table">
                <tr>
                    <th><label for="gallery_name">Nombre de la Galería</label></th>
                    <td>
                        <input type="text" id="gallery_name" name="gallery_name" value="<?php echo esc_attr($gallery_name); ?>" required class="regular-text">
                        <p class="description">Ej: Viaje Salerm Cádiz 2025</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="gallery_slug">Slug (identificador único)</label></th>
                    <td>
                        <input type="text" id="gallery_slug" name="gallery_slug" value="<?php echo esc_attr($gallery_slug); ?>" required class="regular-text">
                        <p class="description">Ej: salerm-cadiz-2025 (solo letras, números y guiones)</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="cloudinary_cloud">Cloudinary Cloud Name</label></th>
                    <td>
                        <input type="text" id="cloudinary_cloud" name="cloudinary_cloud" value="<?php echo esc_attr($cloudinary_cloud); ?>" class="regular-text">
                        <p class="description">Por defecto: galerycordoba</p>
                    </td>
                </tr>
                
                <tr>
                    <th><label for="json_file">Importar JSON</label></th>
                    <td>
                        <input type="file" id="json_file" name="json_file" accept=".json" class="regular-text">
                        <p class="description">
                            Formato esperado: Array de objetos con campos "id", "titulo" y "alt"<br>
                            <code>[{"id":"03_11_25_foto1","titulo":"Foto 1","alt":"Descripción"}]</code>
                        </p>
                        <?php if ($image_count > 0): ?>
                            <p style="color: #28a745;"><strong>✓ Galería actual tiene <?php echo $image_count; ?> imágenes</strong></p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            
            <p>
                <button type="submit" name="save_gallery" class="button button-primary">
                    <?php echo $edit_id ? 'Actualizar Galería' : 'Crear Galería'; ?>
                </button>
                <a href="?page=vmv-galeria-manager" class="button">Cancelar</a>
            </p>
        </form>
    </div>
    <?php
}

// Página de ajustes
function vmv_galeria_settings_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vmv_galleries';
    $mensaje = '';
    $existe = ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name);
    $num_galerias = $existe ? intval($wpdb->get_var("SELECT COUNT(*) FROM $table_name")) : 0;
    $plugin_version = '2.0.0';
    $php_version = phpversion();
    $wp_version = get_bloginfo('version');

    // Crear tabla
    if (isset($_POST['crear_tabla']) && check_admin_referer('vmv_galeria_crear_tabla')) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            gallery_name varchar(255) NOT NULL,
            gallery_slug varchar(191) UNIQUE NOT NULL,
            gallery_data longtext NOT NULL,
            cloudinary_cloud varchar(100) DEFAULT 'galerycordoba',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (gallery_slug)
        ) $charset_collate;";
        error_log('DEBUG VMV: Nombre de tabla: ' . $table_name);
        error_log('DEBUG VMV: SQL generado: ' . $sql);
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        error_log('DEBUG VMV: dbDelta ejecutado');
        $mensaje = '<div class="notice notice-success"><p>Tabla creada (o ya existía).</p></div>';
        $existe = true;
    }
    // Borrar todas las galerías
    if (isset($_POST['borrar_galerias']) && check_admin_referer('vmv_galeria_borrar_galerias')) {
        if ($existe) {
            $wpdb->query("DELETE FROM $table_name");
            $mensaje = '<div class="notice notice-warning"><p>Todas las galerías han sido borradas.</p></div>';
            $num_galerias = 0;
        }
    }
    // Borrar tabla
    if (isset($_POST['borrar_tabla']) && check_admin_referer('vmv_galeria_borrar_tabla')) {
        if ($existe) {
            $wpdb->query("DROP TABLE IF EXISTS $table_name");
            $mensaje = '<div class="notice notice-error"><p>La tabla ha sido eliminada.</p></div>';
            $existe = false;
            $num_galerias = 0;
        }
    }
    ?>
    <div class="wrap">
        <h1>Ajustes de VMV Galerías</h1>
        <?php echo $mensaje; ?>
        <form method="post" style="margin-bottom:1em;">
            <?php wp_nonce_field('vmv_galeria_crear_tabla'); ?>
            <button type="submit" name="crear_tabla" class="button button-primary">Crear tabla de galerías</button>
            <?php if ($existe): ?>
                <span style="color:green;">✓ La tabla existe</span>
            <?php else: ?>
                <span style="color:red;">✗ La tabla no existe</span>
            <?php endif; ?>
        </form>
        <?php if ($existe): ?>
        <form method="post" style="display:inline; margin-right:1em;">
            <?php wp_nonce_field('vmv_galeria_borrar_galerias'); ?>
            <button type="submit" name="borrar_galerias" class="button button-warning" onclick="return confirm('¿Seguro que quieres borrar todas las galerías?');">Borrar todas las galerías</button>
            <span style="color:#555;">Galerías guardadas: <strong><?php echo $num_galerias; ?></strong></span>
        </form>
        <form method="post" style="display:inline;">
            <?php wp_nonce_field('vmv_galeria_borrar_tabla'); ?>
            <button type="submit" name="borrar_tabla" class="button button-danger" onclick="return confirm('¿Seguro que quieres borrar la tabla? Se perderán todas las galerías.');">Borrar tabla</button>
        </form>
        <?php endif; ?>
        <hr>
        <h2>Información del entorno</h2>
        <ul>
            <li><strong>Versión del plugin:</strong> <?php echo $plugin_version; ?></li>
            <li><strong>Versión de WordPress:</strong> <?php echo $wp_version; ?></li>
            <li><strong>Versión de PHP:</strong> <?php echo $php_version; ?></li>
            <li><strong>Prefijo de tabla:</strong> <?php echo $wpdb->prefix; ?></li>
            <li><strong>Nombre de tabla:</strong> <?php echo $table_name; ?></li>
        </ul>
        <hr>
        <p>Puedes usar estos botones para gestionar la tabla y las galerías si tienes problemas con la creación automática.</p>
    </div>
    <?php
}

// Registrar shortcode
add_shortcode('vmv_galeria', 'vmv_galeria_shortcode');

function vmv_galeria_shortcode($atts = []) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vmv_galleries';
    
    $atts = shortcode_atts(['slug' => ''], $atts, 'vmv_galeria');
    
    if (empty($atts['slug'])) {
        return '<p style="color: #E0E0E0; background: #1a1a1a; padding: 10px;">Error: Usa [vmv_galeria slug="nombre-galeria"]</p>';
    }
    
    $gallery = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE gallery_slug = %s",
        $atts['slug']
    ));
    
    if (!$gallery) {
        return '<p style="color: #E0E0E0; background: #1a1a1a; padding: 10px;">Error: Galería no encontrada.</p>';
    }
    
    $images = $gallery->gallery_data;
    $cloudinary = $gallery->cloudinary_cloud;
    
    ob_start();
    ?>
    <style>
        .vmv-gallery-wrapper {
            --img-size: calc(100px - 0.5rem / 2);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: transparent;
            color: #E0E0E0;
        }

        .vmv-gallery-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            padding: 1rem;
            margin: 0 auto;
        }

        @media (min-width: 768px) {
            .vmv-gallery-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 1rem;
                padding: 2rem;
            }
        }

        @media (min-width: 1024px) {
            .vmv-gallery-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 1.25rem;
            }
        }

        .vmv-gallery-item {
            position: relative;
            aspect-ratio: 9 / 16;
            overflow: hidden;
            border-radius: 0.5rem;
            cursor: pointer;
            will-change: transform, box-shadow;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s ease;
        }

        .vmv-gallery-item:hover {
            transform: translate3d(0, 0, 0) scale3d(1.02, 1.02, 1);
            box-shadow: 0 8px 24px rgba(0, 123, 255, 0.3);
        }

        .vmv-gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .vmv-gallery-item:hover img {
            transform: scale3d(1.05, 1.05, 1);
        }

        /* Modal Overlay - Estilo PhotoSwipe/WordPress profesional */
        .vmv-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.96);
            z-index: 9999999;
            overflow: hidden;
            -webkit-overflow-scrolling: touch;
        }

        .vmv-modal.active {
            display: block;
        }

        /* Contenedor principal centrado */
        .vmv-modal-content {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* Botón cerrar - siempre visible arriba a la derecha */
        .vmv-modal-close {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 36px;
            height: 36px;
            background: rgba(0,0,0,0.5);
            border: none;
            border-radius: 50%;
            color: #fff;
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
            z-index: 99999999;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            opacity: 0.85;
        }

        .vmv-modal-close:hover {
            background: rgba(0,0,0,0.8);
            opacity: 1;
        }

        /* Contenedor de imagen - TAMAÑO FIJO */
        .vmv-modal-image-container {
            position: relative;
            width: 90vw;
            height: 70vh;
            max-width: 90vw;
            max-height: 70vh;
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .vmv-modal-image-container img {
           width: 100%;
           height: 100%;
           object-fit: contain;
        }

        /* Imagen - contenida sin cambiar tamaño */
        #vmv-modal-image {
            display: block;
            width: 100%;
            height: 100%;
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            user-select: none;
            -webkit-user-drag: none;
        }

        /* Flechas de navegación */
        .vmv-modal-arrow {
            position: fixed;
            top: 50%;
            transform: translateY(-50%);
            width: 50px;
            height: 50px;
            background: rgba(0, 0, 0, 0.75);
            border: 2px solid rgba(255, 255, 255, 0.5);
            border-radius: 50%;
            color: #FFFFFF;
            cursor: pointer;
            z-index: 99999999;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease, background 0.2s ease;
            opacity: 0.9;
        }

        .vmv-modal-arrow:hover {
            background: rgba(0, 0, 0, 0.9);
            transform: translateY(-50%) scale(1.1);
            opacity: 1;
        }

        .vmv-modal-arrow-prev {
            left: 20px;
        }

        .vmv-modal-arrow-next {
            right: 20px;
        }

        .vmv-modal-arrow svg {
            width: 24px;
            height: 24px;
        }

        /* Botones de acción - en la parte inferior */
        .vmv-modal-actions {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            display: flex;
            gap: 15px;
            justify-content: center;
            align-items: center;
            padding: 20px;
            background: linear-gradient(to top, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.7) 70%, transparent 100%);
            z-index: 99999999;
        }

        .vmv-modal-action-btn {
            width: 46px;
            height: 46px;
            background: rgba(0, 0, 0, 0.75);
            border: 2px solid rgba(255, 255, 255, 0.5);
            border-radius: 50%;
            color: #FFFFFF;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease, background 0.2s ease;
            text-decoration: none;
        }

        .vmv-modal-action-btn:hover {
            background: rgba(0, 0, 0, 0.9);
            transform: translateY(-3px);
        }

        .vmv-modal-action-btn svg {
            width: 22px;
            height: 22px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .vmv-modal-close {
                top: 8px;
                right: 8px;
                width: 32px;
                height: 32px;
                font-size: 18px;
            }

            .vmv-modal-image-container {
                max-width: 95vw;
                max-height: 80vh;
            }

            #vmv-modal-image {
                max-width: 95vw;
                max-height: 80vh;
            }

            .vmv-modal-arrow {
                width: 44px;
                height: 44px;
            }

            .vmv-modal-arrow-prev {
                left: 10px;
            }

            .vmv-modal-arrow-next {
                right: 10px;
            }

            .vmv-modal-arrow svg {
                width: 20px;
                height: 20px;
            }

            .vmv-modal-actions {
                padding: 15px;
                gap: 12px;
            }

            .vmv-modal-action-btn {
                width: 42px;
                height: 42px;
            }

            .vmv-modal-action-btn svg {
                width: 20px;
                height: 20px;
            }
        }

        @media (max-width: 480px) {
            .vmv-modal-close {
                top: 4px;
                right: 4px;
                width: 24px;
                height: 24px;
                font-size: 12px;
            }

            .vmv-modal-image-container {
                max-width: 100vw;
                max-height: 75vh;
            }

            #vmv-modal-image {
                max-width: 100vw;
                max-height: 75vh;
            }

            .vmv-modal-arrow {
                width: 40px;
                height: 40px;
                opacity: 0.8;
            }

            .vmv-modal-arrow-prev {
                left: 5px;
            }

            .vmv-modal-arrow-next {
                right: 5px;
            }

            .vmv-modal-arrow svg {
                width: 18px;
                height: 18px;
            }

            .vmv-modal-actions {
                padding: 12px;
                gap: 10px;
            }

            .vmv-modal-action-btn {
                width: 38px;
                height: 38px;
            }

            .vmv-modal-action-btn svg {
                width: 18px;
                height: 18px;
            }
        }

        /* Prevenir scroll del body cuando modal está abierto */
        body.vmv-modal-open {
            overflow: hidden !important;
            position: fixed !important;
            width: 100% !important;
        }

        .vmv-gallery-footer {
            text-align: center;
            padding: 2rem 1rem;
            color: rgba(224, 224, 224, 0.5);
            font-size: 0.875rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
    </style>


    <div class="vmv-gallery-wrapper">
        <!-- Gallery Grid -->
        <div id="vmv-gallery-<?php echo esc_attr($atts['slug']); ?>" class="vmv-gallery-grid"></div>

        <!-- Modal -->
        <div id="vmv-modal-<?php echo esc_attr($atts['slug']); ?>" class="vmv-modal">
            <button class="vmv-modal-close" onclick="vmvCloseModal('<?php echo esc_attr($atts['slug']); ?>')">&times;</button>
            <button class="vmv-modal-arrow vmv-modal-arrow-prev" onclick="vmvShowPrevImage('<?php echo esc_attr($atts['slug']); ?>')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
            <button class="vmv-modal-arrow vmv-modal-arrow-next" onclick="vmvShowNextImage('<?php echo esc_attr($atts['slug']); ?>')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>

            <div class="vmv-modal-content">
                <div class="vmv-modal-image-container" style="position:relative;">
                    <button class="vmv-modal-close" onclick="vmvCloseModal('<?php echo esc_attr($atts['slug']); ?>')">&times;</button>
                    <img id="vmv-modal-image-<?php echo esc_attr($atts['slug']); ?>" src="" alt="Imagen ampliada">
                </div>

                <div class="vmv-modal-actions">
                    <button class="vmv-modal-action-btn" onclick="vmvDownloadImage('<?php echo esc_attr($atts['slug']); ?>')" title="Descargar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                    </button>
                    <button class="vmv-modal-action-btn" onclick="vmvShareWhatsApp('<?php echo esc_attr($atts['slug']); ?>')" title="WhatsApp">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                    </button>
                    <button class="vmv-modal-action-btn" onclick="vmvShareFacebook('<?php echo esc_attr($atts['slug']); ?>')" title="Facebook">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                    </button>
                    <button class="vmv-modal-action-btn" onclick="vmvShareInstagram('<?php echo esc_attr($atts['slug']); ?>')" title="Instagram">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.VMV_GALLERIES = window.VMV_GALLERIES || {};
        
        if (!window.VMV_GALLERIES['<?php echo esc_js($atts['slug']); ?>']) {
            window.VMV_GALLERIES['<?php echo esc_js($atts['slug']); ?>'] = {
                cloudinary: '<?php echo esc_js($cloudinary); ?>',
                images: <?php echo $images; ?>,
                currentIndex: 0
            };
        }

        function vmvGetCloudinaryUrl(slug, imageId, width = 400, height = 711) {
            const cloudinary = window.VMV_GALLERIES[slug].cloudinary;
            return `https://res.cloudinary.com/${cloudinary}/image/upload/w_${width},h_${height},c_fill,q_auto,f_auto/${imageId}`;
        }

        function vmvLoadGallery(slug) {
            const gallery = document.getElementById('vmv-gallery-' + slug);
            const images = window.VMV_GALLERIES[slug].images;
            
            if (!gallery || !images) return;
            
            gallery.innerHTML = '';
            images.forEach((image, index) => {
                const imageUrl = vmvGetCloudinaryUrl(slug, image.id, 400, 711);
                const div = document.createElement('div');
                div.className = 'vmv-gallery-item';
                div.onclick = () => vmvOpenModal(slug, index);

                const img = document.createElement('img');
                img.src = imageUrl;
                img.alt = image.alt;
                img.title = image.titulo;
                img.loading = 'lazy';

                div.appendChild(img);
                gallery.appendChild(div);
            });
        }

        function vmvOpenModal(slug, index) {
            window.VMV_GALLERIES[slug].currentIndex = index;
            const modal = document.getElementById('vmv-modal-' + slug);
            const modalImage = document.getElementById('vmv-modal-image-' + slug);
            const image = window.VMV_GALLERIES[slug].images[index];

            const lowResUrl = vmvGetCloudinaryUrl(slug, image.id, 400, 711);
            const highResUrl = vmvGetCloudinaryUrl(slug, image.id, 1200, 2133);

            // Cargar imagen baja resolución primero
            modalImage.src = lowResUrl;
            modalImage.alt = image.titulo;
            
            // Mostrar modal sin modificar el scroll
            modal.classList.add('active');

            // Cargar imagen alta resolución en background
            const imgHigh = new Image();
            imgHigh.src = highResUrl;
            imgHigh.onload = () => {
                // Solo cambia src, NUNCA modifica tamaño ni contenedor
                modalImage.src = highResUrl;
            };
        }

        function vmvCloseModal(slug) {
            const modal = document.getElementById('vmv-modal-' + slug);
            modal.classList.remove('active');
        }

        function vmvShowNextImage(slug) {
            const images = window.VMV_GALLERIES[slug].images;
            window.VMV_GALLERIES[slug].currentIndex = (window.VMV_GALLERIES[slug].currentIndex + 1) % images.length;
            vmvOpenModal(slug, window.VMV_GALLERIES[slug].currentIndex);
        }

        function vmvShowPrevImage(slug) {
            const images = window.VMV_GALLERIES[slug].images;
            window.VMV_GALLERIES[slug].currentIndex = (window.VMV_GALLERIES[slug].currentIndex - 1 + images.length) % images.length;
            vmvOpenModal(slug, window.VMV_GALLERIES[slug].currentIndex);
        }

        function vmvDownloadImage(slug) {
            const image = window.VMV_GALLERIES[slug].images[window.VMV_GALLERIES[slug].currentIndex];
            const highResUrl = vmvGetCloudinaryUrl(slug, image.id, 1200, 2133);
            // Descargar usando fetch y blob para forzar descarga en todos los navegadores
            fetch(highResUrl)
                .then(response => response.blob())
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = (image.titulo ? image.titulo.replace(/\s+/g, '_') : 'foto') + '.jpg';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    window.URL.revokeObjectURL(url);
                });
        }

        function vmvShareWhatsApp(slug) {
            const image = window.VMV_GALLERIES[slug].images[window.VMV_GALLERIES[slug].currentIndex];
            const highResUrl = vmvGetCloudinaryUrl(slug, image.id, 1200, 2133);
            const text = encodeURIComponent('Salerm me ha invitado a Cádiz, mira este momento: ' + highResUrl);
            window.open(`https://wa.me/?text=${text}`, '_blank');
        }

        function vmvShareFacebook(slug) {
            const image = window.VMV_GALLERIES[slug].images[window.VMV_GALLERIES[slug].currentIndex];
            const highResUrl = vmvGetCloudinaryUrl(slug, image.id, 1200, 2133);
            const text = encodeURIComponent('Salerm me ha invitado a Cádiz, mira este momento: ' + highResUrl);
            window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(highResUrl)}&quote=${text}`, '_blank');
        }

        function vmvShareInstagram(slug) {
            // Instagram no permite compartir por web, solo abrir perfil
            window.open('https://www.instagram.com/', '_blank');
        }

        document.addEventListener('DOMContentLoaded', () => {
            vmvLoadGallery('<?php echo esc_js($atts['slug']); ?>');

            const slug = '<?php echo esc_js($atts['slug']); ?>';
            const modal = document.getElementById('vmv-modal-' + slug);
            // Mejorar cierre: botón cerrar y overlay
            if (modal) {
                // Overlay click
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) vmvCloseModal(slug);
                });
                // Botón cerrar
                const closeBtn = modal.querySelector('.vmv-modal-close');
                if (closeBtn) {
                    closeBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        vmvCloseModal(slug);
                    });
                }
            }

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') vmvCloseModal(slug);
                if (e.key === 'ArrowLeft') vmvShowPrevImage(slug);
                if (e.key === 'ArrowRight') vmvShowNextImage(slug);
            });
        });
    </script>
    <?php
    return ob_get_clean();
}
