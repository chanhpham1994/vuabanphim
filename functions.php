<?php
/**
 * Blocksy functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Blocksy
 */

if (version_compare(PHP_VERSION, '5.7.0', '<')) {
	require get_template_directory() . '/inc/php-fallback.php';
	return;
}

require get_template_directory() . '/inc/init.php';


function them_trang_admin_bar($wp_admin_bar) {
    // Thêm một mục mới vào admin bar
    $args = array(
        'id'    => 2624, // ID duy nhất cho mục
        'title' => 'Đóng Hàng', // Tiêu đề hiển thị trên admin bar
        'href'  => home_url().'/dong-hang', // Đường dẫn khi nhấp vào
        'meta'  => array(
            'target' => '_blank', // Mở liên kết trong tab mới (tùy chọn)
            'class'  => 'dong-hang-class' // Class CSS tùy chỉnh (tùy chọn)
        )
    );
    $wp_admin_bar->add_node($args);
}

add_action('admin_bar_menu', 'them_trang_admin_bar', 999);


function check_and_create_invoice_sku_logs_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'invoice_sku_logs';
    $charset_collate = $wpdb->get_charset_collate();

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            user_name varchar(100) NOT NULL DEFAULT '',
            datetime datetime NOT NULL,
            invoice_code varchar(50) NOT NULL,
            sku_code varchar(50) NOT NULL,
            product_name varchar(50) NOT NULL,
            quantity bigint(20) NOT NULL,
            delivery_code varchar(50) NOT NULL,
            match_status tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    } else {
        // Kiểm tra và thêm cột user_name nếu chưa có
        if (!$wpdb->get_var("SHOW COLUMNS FROM `$table_name` LIKE 'user_name'")) {
            $wpdb->query("ALTER TABLE `$table_name` ADD COLUMN `user_name` VARCHAR(100) NOT NULL DEFAULT '' AFTER `user_id`");
        }
    }
}
add_action('init', 'check_and_create_invoice_sku_logs_table');

// (Giữ nguyên phần code shortcode và các hàm khác ở đây)

?>

<?php
// Thêm menu vào Admin Dashboard
function register_export_sku_logs_menu() {
    add_menu_page(
        'Export SKU Logs',
        'Export SKU Logs',
        'administrator',
        'export-sku-logs',
        'export_sku_logs_page',
        'dashicons-download',
        80
    );
}
add_action('admin_menu', 'register_export_sku_logs_menu');

// Nội dung trang Export SKU Logs
function export_sku_logs_page() {
    if (!current_user_can('administrator')) {
        wp_die('Bạn không có quyền truy cập trang này.');
    }
    ?>
    <div class="wrap">
        <h1>Xuất dữ liệu SKU Logs</h1>
        <p>Chọn khoảng thời gian để xuất dữ liệu từ bảng <code>wp_invoice_sku_logs</code> thành file CSV.</p>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <table class="form-table">
                <tr>
                    <th><label for="start_date">Ngày bắt đầu</label></th>
                    <td><input type="date" name="start_date" id="start_date" value="<?php echo esc_attr(date('Y-m-d', strtotime('-30 days'))); ?>" required></td>
                </tr>
                <tr>
                    <th><label for="end_date">Ngày kết thúc</label></th>
                    <td><input type="date" name="end_date" id="end_date" value="<?php echo esc_attr(date('Y-m-d')); ?>" required></td>
                </tr>
            </table>
            <input type="hidden" name="action" value="export_sku_logs_csv">
            <?php submit_button('Xuất file CSV', 'primary'); ?>
        </form>
    </div>
    <?php
}

// Đăng ký hành động xuất CSV
add_action('admin_post_export_sku_logs_csv', 'export_sku_logs_to_csv');

// Hàm xuất dữ liệu thành CSV
function export_sku_logs_to_csv() {
    if (!current_user_can('administrator')) {
        wp_die('Bạn không có quyền thực hiện hành động này.');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'invoice_sku_logs';

    // Lấy giá trị ngày từ form
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
    $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';

    // Xây dựng truy vấn SQL với bộ lọc thời gian và sắp xếp theo user_name, invoice_code
    if ($start_date && $end_date) {
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE datetime BETWEEN %s AND %s ORDER BY user_name ASC, invoice_code ASC, datetime ASC",
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        );
    } else {
        $query = "SELECT * FROM $table_name ORDER BY user_name ASC, invoice_code ASC, datetime ASC";
    }

    $results = $wpdb->get_results($query, ARRAY_A);

    if (empty($results)) {
        wp_redirect(admin_url('admin.php?page=export-sku-logs&error=no_data'));
        exit;
    }

    // Tạo file CSV
    $filename = 'sku_logs_' . $start_date . '_to_' . $end_date . '_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=utf-16le');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Mở stream để ghi dữ liệu
    $output = fopen('php://output', 'w');

    // Thêm BOM cho UTF-16LE (hỗ trợ tiếng Việt trên Excel)
    fprintf($output, chr(0xFF) . chr(0xFE));

    // Tiêu đề cột
    $columns = array('ID', 'User ID', 'User Name', 'DateTime', 'Invoice Code', 'SKU Code', 'Product Name', 'Quantity', 'Delivery Code', 'Match Status');
    fputcsv($output, $columns);

    // Xử lý dữ liệu để tránh trùng lặp dựa trên user_name và invoice_code
    $previous_key = null;
    foreach ($results as $row) {
        $current_key = $row['user_name'] . '|' . $row['invoice_code']; // Kết hợp user_name và invoice_code làm khóa
        if ($previous_key === $current_key) {
            // Nếu trùng user_name và invoice_code, chỉ in SKU Code, Product Name, Quantity và Match Status
            $line = array(
                '', // ID
                '', // User ID
                '', // User Name
                '', // DateTime
                '', // Invoice Code
                $row['sku_code'], // SKU Code
                $row['product_name'], // Product Name
                $row['quantity'], // Quantity
                '', // Delivery Code
                $row['match_status'] ? 'Khớp' : 'Không khớp' // Match Status
            );
            fputcsv($output, $line);
        } else {
            // Nếu khác, in đầy đủ thông tin
            $line = array(
                $row['id'],
                $row['user_id'],
                $row['user_name'],
                $row['datetime'],
                $row['invoice_code'],
                $row['sku_code'],
                $row['product_name'], // Product Name
                $row['quantity'], // Quantity
                $row['delivery_code'],
                $row['match_status'] ? 'Khớp' : 'Không khớp'
            );
            fputcsv($output, $line);
            $previous_key = $current_key;
        }
    }

    fclose($output);
    exit();
}

// Xử lý thông báo lỗi (nếu không có dữ liệu)
add_action('admin_notices', 'export_sku_logs_admin_notices');
function export_sku_logs_admin_notices() {
    if (isset($_GET['page']) && $_GET['page'] === 'export-sku-logs' && isset($_GET['error']) && $_GET['error'] === 'no_data') {
        echo '<div class="notice notice-warning is-dismissible"><p>Không có dữ liệu trong khoảng thời gian đã chọn.</p></div>';
    }
}
?>