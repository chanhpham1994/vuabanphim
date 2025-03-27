<?php
  /**
   * Template Name: Page Dong Hang
   *
   * This is the most generic template file in a WordPress theme
   * and one of the two required files for a theme (the other being style.css).
   * It is used to display a page when nothing more specific matches a query.
   * E.g., it puts together the home page when no home.php file exists.
   *
   */
  $clientId = '22f58391-00fa-4db1-ac09-9940ffe39b53'; // Thay bằng client_id thực tế
  $clientSecret = '6FED2DD6AD67FEBAD8C3ED9709B44D110E0EFA40'; // Thay bằng client_secret thực tế
  $retailer = 'vuabanphim'; // Thay bằng retailer_id thực tế
  $url = "https://public.kiotapi.com/invoices?fromPurchaseDate={$startDate}&toPurchaseDate={$endDate}&pageSize=1000&includeInvoiceDelivery=true";
?>	
  
  <link rel="stylesheet" href="<?php echo get_template_directory_uri(); ?>/css/invoice-sku-checker.css">


<?php
  
 // Đăng ký shortcode [invoice_sku_checker]
function invoice_sku_checker_shortcode() {
    ob_start();
    session_start();

    // Làm mới session khi reload trang hoặc nhấn Reset
    if (!isset($_POST['submit_delivery']) && !isset($_POST['check_sku']) && 
        !isset($_POST['exchange_sku']) && !isset($_POST['cancel_sku']) && 
        !isset($_POST['complete']) && !isset($_POST['delete_sku']) && 
        !isset($_POST['reset_session']) && !isset($_POST['confirm_save'])) {
        unset($_SESSION['current_delivery_code']);
        unset($_SESSION['temp_data']);
        unset($_SESSION['show_buttons']);
        unset($_SESSION['last_sku']);
        unset($_SESSION['data_saved']);
        unset($_SESSION['show_confirm']);
    }

    // Xử lý nút "Reset"
    if (isset($_POST['reset_session'])) {
        unset($_SESSION['current_delivery_code']);
        unset($_SESSION['temp_data']);
        unset($_SESSION['show_buttons']);
        unset($_SESSION['last_sku']);
        unset($_SESSION['data_saved']);
        unset($_SESSION['show_confirm']);
        echo '<p class="invoice-sku-message success">Đã reset toàn bộ dữ liệu tạm.</p>';
    }

    // Thông tin xác thực API
    $clientId = '22f58391-00fa-4db1-ac09-9940ffe39b53';
    $clientSecret = '6FED2DD6AD67FEBAD8C3ED9709B44D110E0EFA40';
    $retailer = 'vuabanphim';

    $token = get_kiotviet_token($clientId, $clientSecret);
    if (!$token) {
        echo '<p class="invoice-sku-message error">Không thể lấy token.</p>';
        return ob_get_clean();
    }

    $invoiceData = null;
    if (isset($_POST['submit_delivery']) && !empty($_POST['delivery_code'])) {
        $deliveryCode = sanitize_text_field($_POST['delivery_code']);
        if (!isset($_SESSION['current_delivery_code']) || $_SESSION['current_delivery_code'] !== $deliveryCode) {
            unset($_SESSION['temp_data']);
            unset($_SESSION['data_saved']);
            unset($_SESSION['show_buttons']);
            unset($_SESSION['last_sku']);
            unset($_SESSION['show_confirm']);
        }
        $_SESSION['current_delivery_code'] = $deliveryCode;
        $allInvoices = get_all_invoices($token, $retailer);
        $invoiceData = find_invoice_by_delivery_code($allInvoices, $deliveryCode);
    } elseif (isset($_SESSION['current_delivery_code'])) {
        $deliveryCode = $_SESSION['current_delivery_code'];
        $allInvoices = get_all_invoices($token, $retailer);
        $invoiceData = find_invoice_by_delivery_code($allInvoices, $deliveryCode);
    }

    // Xử lý nút "Kiểm tra"
    if (isset($_POST['check_sku']) && !empty($_POST['sku_code'])) {
        $skuCode = sanitize_text_field($_POST['sku_code']);
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1; // Mặc định 1 nếu không nhập
        $deliveryCode = $_SESSION['current_delivery_code'] ?? '';
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        $currentTime = current_time('mysql');
        $invoiceCode = $invoiceData['code'] ?? 'N/A';
        $deliveryCodeFromInvoice = $invoiceData['invoiceDelivery']['deliveryCode'] ?? 'N/A';

        if ($invoiceData && isset($invoiceData['invoiceDetails'])) {
            $matchFound = false;
            $productName = 'N/A';
            foreach ($invoiceData['invoiceDetails'] as $product) {
                if ($product['productCode'] === $skuCode && $product['quantity'] === $quantity) {
                    $matchFound = true;
                    $productName = $product['productName'] ?? 'N/A';
                    break;
                } elseif ($product['productCode'] === $skuCode) {
                    $productName = $product['productName'] ?? 'N/A'; // Lấy tên sản phẩm dù số lượng không khớp
                }
            }

            if ($matchFound) {
                $_SESSION['temp_data'][] = [
                    'datetime' => $currentTime,
                    'invoice_code' => $invoiceCode,
                    'sku_code' => $skuCode,
                    'product_name' => $productName, // Thêm tên sản phẩm
                    'quantity' => $quantity, // Thêm số lượng
                    'delivery_code' => $deliveryCodeFromInvoice,
                    'match' => true
                ];
            } else {
                $_SESSION['show_buttons'] = true;
                $_SESSION['last_sku'] = $skuCode;
                $_SESSION['last_quantity'] = $quantity; // Lưu số lượng để dùng khi "Đổi"
                $_SESSION['last_product_name'] = $productName; // Lưu tên sản phẩm để dùng khi "Đổi"
            }
        }
    }

    // Xử lý nút "Đổi"
    if (isset($_POST['exchange_sku']) && isset($_SESSION['last_sku'])) {
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        $currentTime = current_time('mysql');
        $invoiceCode = $invoiceData['code'] ?? 'N/A';
        $deliveryCode = $invoiceData['invoiceDelivery']['deliveryCode'] ?? 'N/A';
        $_SESSION['temp_data'][] = [
            'datetime' => $currentTime,
            'invoice_code' => $invoiceCode,
            'sku_code' => $_SESSION['last_sku'],
            'product_name' => $_SESSION['last_product_name'] ?? 'N/A', // Thêm tên sản phẩm
            'quantity' => $_SESSION['last_quantity'] ?? 1, // Thêm số lượng
            'delivery_code' => $deliveryCode,
            'match' => false
        ];
        unset($_SESSION['show_buttons']);
        unset($_SESSION['last_sku']);
        unset($_SESSION['last_quantity']);
        unset($_SESSION['last_product_name']);
    }

    // Xử lý nút "Hủy"
    if (isset($_POST['cancel_sku'])) {
        unset($_SESSION['show_buttons']);
        unset($_SESSION['last_sku']);
        unset($_SESSION['last_quantity']);
        unset($_SESSION['last_product_name']);
    }

    // Xử lý nút "Xóa" SKU tạm
    if (isset($_POST['delete_sku']) && isset($_POST['sku_index']) && isset($_SESSION['temp_data'])) {
        $index = intval($_POST['sku_index']);
        if (isset($_SESSION['temp_data'][$index])) {
            unset($_SESSION['temp_data'][$index]);
            $_SESSION['temp_data'] = array_values($_SESSION['temp_data']);
            echo '<p class="invoice-sku-message success">Đã xóa mã SKU khỏi danh sách tạm.</p>';
        }
    }

    // Xử lý nút "Hoàn Thành" và xác nhận
    if (isset($_POST['complete']) && !empty($_SESSION['temp_data'])) {
        if (!isset($_SESSION['data_saved']) || $_SESSION['data_saved'] !== true) {
            $hasMismatch = false;
            foreach ($_SESSION['temp_data'] as $item) {
                if (!$item['match']) {
                    $hasMismatch = true;
                    break;
                }
            }

            if ($hasMismatch && !isset($_SESSION['show_confirm'])) {
                $_SESSION['show_confirm'] = true;
            } elseif (!$hasMismatch) {
                save_to_database($_SESSION['temp_data']);
                $_SESSION['data_saved'] = true;
                unset($_SESSION['temp_data']);
                unset($_SESSION['current_delivery_code']);
                unset($_SESSION['show_confirm']);
                echo '<p class="invoice-sku-message success">Đã lưu thông tin vào database.</p>';
            }
        } else {
            echo '<p class="invoice-sku-message warning">Dữ liệu đã được lưu trước đó, không lưu lại.</p>';
        }
    }

    // Xử lý xác nhận lưu
    if (isset($_POST['confirm_save']) && $_SESSION['show_confirm']) {
        if ($_POST['confirm_save'] === 'yes') {
            save_to_database($_SESSION['temp_data']);
            $_SESSION['data_saved'] = true;
            unset($_SESSION['temp_data']);
            unset($_SESSION['current_delivery_code']);
            unset($_SESSION['show_confirm']);
            echo '<p class="invoice-sku-message success">Đã lưu thông tin vào database.</p>';
        } else {
            unset($_SESSION['show_confirm']);
            echo '<p class="invoice-sku-message warning">Đã hủy lưu dữ liệu.</p>';
        }
    }

    // Giao diện
    ?>
    <div class="invoice-sku-container">
        <!-- Nút Reset -->
        <form method="post" action="">
            <input type="submit" name="reset_session" value="Reset" class="invoice-sku-button reset">
        </form>

        <!-- Form mã vận đơn -->
        <form method="post" action="" class="invoice-sku-form">
            <label for="delivery_code">Nhập mã vận đơn:</label>
            <input type="text" name="delivery_code" id="delivery_code" value="" required>
            <input type="submit" name="submit_delivery" value="Tìm kiếm" class="invoice-sku-button">
        </form>

        <?php if ($invoiceData && isset($invoiceData['invoiceDetails'])) { ?>
            <h2>Thông tin sản phẩm trong hóa đơn <?php echo esc_html($invoiceData['code']); ?></h2>
            <table class="invoice-sku-table">
                <tr>
                    <th>Tên sản phẩm</th>
                    <th>Mã sản phẩm</th>
                    <th>Số lượng</th>
                    <th>Đơn giá</th>
                    <th>Tổng tiền</th>
                </tr>
                <?php 
                $tempSkus = array_column($_SESSION['temp_data'] ?? [], 'sku_code');
                foreach ($invoiceData['invoiceDetails'] as $product) {
                    $style = in_array($product['productCode'], $tempSkus) ? 'color: #4caf50;' : '';
                ?>
                    <tr>
                        <td style="<?php echo $style; ?>"><?php echo esc_html($product['productName'] ?? 'N/A'); ?></td>
                        <td style="<?php echo $style; ?>"><?php echo esc_html($product['productCode'] ?? 'N/A'); ?></td>
                        <td><?php echo esc_html($product['quantity'] ?? 0); ?></td>
                        <td><?php echo number_format($product['price'] ?? 0, 0, ',', '.'); ?> VNĐ</td>
                        <td><?php echo number_format($product['total'] ?? ($product['quantity'] * $product['price']), 0, ',', '.'); ?> VNĐ</td>
                    </tr>
                <?php } ?>
            </table>
        <?php } elseif (isset($_POST['submit_delivery'])) { ?>
            <p class="invoice-sku-message error">Không tìm thấy hóa đơn với mã vận đơn này.</p>
        <?php } ?>

        <!-- Form kiểm tra SKU -->
        <form method="post" action="" class="invoice-sku-form">
            <label for="sku_code">Nhập mã SKU:</label>
            <input type="text" name="sku_code" id="sku_code" value="" required>
            <label for="quantity">Số lượng:</label>
            <input type="number" name="quantity" id="quantity" value="1" min="1" required>
            <input type="submit" name="check_sku" value="Kiểm tra" class="invoice-sku-button">
        </form>

        <!-- Danh sách SKU tạm -->
        <?php if (!empty($_SESSION['temp_data'])) { ?>
            <h3>Danh sách SKU đang lưu tạm</h3>
            <table class="invoice-sku-table">
                <tr>
                    <th>Thời gian</th>
                    <th>Mã hóa đơn</th>
                    <th>Tên sản phẩm</th> <!-- Thêm cột Tên sản phẩm -->
                    <th>Mã SKU</th>
                    <th>Số lượng</th> <!-- Thêm cột Số lượng -->
                    <th>Mã vận đơn</th>
                    <th>Trạng thái</th>
                    <th>Hành động</th>
                </tr>
                <?php foreach ($_SESSION['temp_data'] as $index => $item) { ?>
                    <tr>
                        <td><?php echo esc_html($item['datetime']); ?></td>
                        <td><?php echo esc_html($item['invoice_code']); ?></td>
                        <td><?php echo esc_html($item['product_name']); ?></td> <!-- Hiển thị tên sản phẩm -->
                        <td><?php echo esc_html($item['sku_code']); ?></td>
                        <td><?php echo esc_html($item['quantity']); ?></td> <!-- Hiển thị số lượng -->
                        <td><?php echo esc_html($item['delivery_code']); ?></td>
                        <td class="<?php echo $item['match'] ? 'match' : 'no-match'; ?>">
                            <?php echo $item['match'] ? 'Khớp' : 'Không khớp'; ?>
                        </td>
                        <td>
                            <form method="post" action="">
                                <input type="hidden" name="sku_index" value="<?php echo $index; ?>">
                                <input type="submit" name="delete_sku" value="Xóa" class="invoice-sku-button delete-button">
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </table>
        <?php } ?>

        <!-- Nút Đổi/Hủy -->
        <?php if (isset($_SESSION['show_buttons']) && $_SESSION['show_buttons']) { ?>
            <form method="post" action="" class="invoice-sku-action-buttons">
                <input type="submit" name="exchange_sku" value="Đổi" class="invoice-sku-button">
                <input type="submit" name="cancel_sku" value="Hủy" class="invoice-sku-button">
            </form>
        <?php } ?>

        <!-- Form xác nhận nếu có SKU không khớp -->
        <?php if (!empty($_SESSION['temp_data']) && isset($_SESSION['show_confirm']) && $_SESSION['show_confirm']) { ?>
            <div class="invoice-sku-confirm">
                <p>Danh sách SKU có mã không khớp. Bạn có muốn tiếp tục lưu vào database không?</p>
                <div class="invoice-sku-confirm-buttons">
                    <form method="post" action="">
                        <input type="hidden" name="confirm_save" value="yes">
                        <input type="submit" value="Lưu" class="invoice-sku-button">
                    </form>
                    <form method="post" action="">
                        <input type="hidden" name="confirm_save" value="no">
                        <input type="submit" value="Hủy" class="invoice-sku-button">
                    </form>
                </div>
            </div>
        <?php } elseif (!empty($_SESSION['temp_data'])) { ?>
            <!-- Form Hoàn Thành -->
            <form id="complete_form" method="post" action="">
                <input type="submit" name="complete" value="Hoàn Thành" class="invoice-sku-button invoice-sku-complete">
            </form>
        <?php } ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('invoice_sku_checker', 'invoice_sku_checker_shortcode');

// Hàm lưu vào database
function save_to_database($data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'invoice_sku_logs';
    $user_id = get_current_user_id();
    $user_info = get_userdata($user_id);
    $user_name = $user_info ? $user_info->user_login : 'Unknown';

    date_default_timezone_set('Asia/Ho_Chi_Minh');
    foreach ($data as $item) {
        $wpdb->insert(
            $table_name,
            [
                'user_id' => $user_id,
                'user_name' => $user_name,
                'datetime' => $item['datetime'],
                'invoice_code' => $item['invoice_code'],
                'sku_code' => $item['sku_code'],
                'product_name' => $item['product_name'], // Thêm product_name
                'quantity' => $item['quantity'], // Thêm quantity
                'delivery_code' => $item['delivery_code'],
                'match_status' => $item['match'] ? 1 : 0
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d'] // Cập nhật định dạng
        );
    }
}

// Hàm lấy token từ KiotViet
function get_kiotviet_token($clientId, $clientSecret) {
    $data = [
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'scope' => 'PublicApi.Access'
    ];

    $ch = curl_init('https://id.kiotviet.vn/connect/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response && $httpCode === 200) {
        $responseData = json_decode($response, true);
        return $responseData['access_token'] ?? null;
    }
    return null;
}

// Hàm lấy danh sách hóa đơn trong 7 ngày gần nhất
function get_all_invoices($token, $retailer) {
    $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime('-7 days'));
    $url = "https://public.kiotapi.com/invoices?fromPurchaseDate={$startDate}&toPurchaseDate={$endDate}&pageSize=1000&includeInvoiceDelivery=true";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Retailer: ' . $retailer,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response && $httpCode === 200) {
        $data = json_decode($response, true);
        return $data['data'] ?? [];
    }
    return [];
}

// Hàm tìm hóa đơn theo delivery_code
function find_invoice_by_delivery_code($invoices, $deliveryCode) {
    foreach ($invoices as $invoice) {
        if (isset($invoice['invoiceDelivery']['deliveryCode']) && $invoice['invoiceDelivery']['deliveryCode'] === $deliveryCode) {
            return $invoice;
        }
    }
    return null;
}

echo do_shortcode( '[invoice_sku_checker]' );



// Shortcode tìm kiếm thông tin từ wp_invoice_sku_logs
function invoice_sku_search_shortcode() {
    ob_start();
    global $wpdb;
    $table_name = $wpdb->prefix . 'invoice_sku_logs';
    $search_results = null;

    // Xử lý tìm kiếm
    if (isset($_POST['search_sku_logs']) && !empty($_POST['search_term'])) {
        $search_term = sanitize_text_field($_POST['search_term']);
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE invoice_code = %s OR delivery_code = %s ORDER BY datetime DESC",
            $search_term,
            $search_term
        );
        $search_results = $wpdb->get_results($query, ARRAY_A);

        if (empty($search_results)) {
            echo '<p class="invoice-sku-message warning">Không tìm thấy dữ liệu với mã "' . esc_html($search_term) . '".</p>';
        }
    }

    ?>
    <div class="invoice-sku-container">
        <h2>Tìm kiếm thông tin đã lưu</h2>
        <form method="post" action="" class="invoice-sku-form">
            <label for="search_term">Nhập mã hóa đơn hoặc mã vận đơn:</label>
            <input type="text" name="search_term" id="search_term" value="" required>
            <input type="submit" name="search_sku_logs" value="Tìm kiếm" class="invoice-sku-button">
        </form>

        <?php if (!empty($search_results)) { ?>
            <h3>Kết quả tìm kiếm</h3>
            <table class="invoice-sku-table">
                <tr>
                    <th>ID</th>
                    <th>User ID</th>
                    <th>User Name</th>
                    <th>Thời gian</th>
                    <th>Mã hóa đơn</th>
                    <th>Mã SKU</th>
                    <th>Tên sản phẩm</th>
                    <th>Số lượng</th>
                    <th>Mã vận đơn</th>
                    <th>Trạng thái</th>
                </tr>
                <?php foreach ($search_results as $row) { ?>
                    <tr>
                        <td><?php echo esc_html($row['id']); ?></td>
                        <td><?php echo esc_html($row['user_id']); ?></td>
                        <td><?php echo esc_html($row['user_name']); ?></td>
                        <td><?php echo esc_html($row['datetime']); ?></td>
                        <td><?php echo esc_html($row['invoice_code']); ?></td>
                        <td><?php echo esc_html($row['sku_code']); ?></td>
                        <td><?php echo esc_html($row['product_name']); ?></td>
                        <td><?php echo esc_html($row['quantity']); ?></td>
                        <td><?php echo esc_html($row['delivery_code']); ?></td>
                        <td class="<?php echo $row['match_status'] ? 'match' : 'no-match'; ?>">
                            <?php echo $row['match_status'] ? 'Khớp' : 'Không khớp'; ?>
                        </td>
                    </tr>
                <?php } ?>
            </table>
        <?php } ?>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('invoice_sku_search', 'invoice_sku_search_shortcode');


echo do_shortcode('[invoice_sku_search]');