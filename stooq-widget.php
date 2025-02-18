<?php
/**
 * Plugin Name: Stooq Stock Widget
 * Description: Pobiera i wyświetla dane giełdowe z Stooq.pl
 * Version: 1.0
 * Author: Twoje Imię
 */

if (!defined('ABSPATH')) {
    exit; // Ochrona przed bezpośrednim dostępem
}

function get_db_data($symbol)
{
    global $wpdb;
    $table_name = 'stooq'; // Upewnij się, że nazwa tabeli ma prefiks WP
    $query = $wpdb->prepare("SELECT * FROM $table_name WHERE name = %s", $symbol);
    $results = $wpdb->get_results($query, ARRAY_A);

    foreach ($results as $row) {
        echo '<pre>' . print_r($row, true) . '</pre>';
    }

}
function add_stock_data($stock_data)
{
    global $wpdb;
    $table_name = 'stooq'; // Upewnij się, że nazwa tabeli ma prefiks WP

    // Sprawdzenie wymaganych pól
    if (!isset($stock_data['symbol'], $stock_data['date'], $stock_data['open'], $stock_data['close'])) {
        return "Błąd: Brak wymaganych danych (symbol, date, open, close)";
    }

    // Wstawianie danych do tabeli
    // Sanitizacja danych
    $symbol = sanitize_text_field($stock_data['symbol']);
    $date   = sanitize_text_field($stock_data['date']);
    $open   = intval($stock_data['open']);
    $close  = intval($stock_data['close']);

    // Zapytanie SQL
    $query = $wpdb->prepare(
        "INSERT INTO $table_name (`name`, `date`, `open`, `close`) 
         VALUES (%s, %s, %d, %d) 
         ON DUPLICATE KEY UPDATE 
         `open` = VALUES(`open`), `close` = VALUES(`close`)",
        $symbol, $date, $open, $close
    );
    $result = $wpdb->query($query);

    
}

// Rejestracja shortcode
function stooq_stock_shortcode($atts)
{
    $atts = shortcode_atts(
        array('symbol' => 'ene'),
        $atts,
        'stooq_stock'
    );

    $symbol = strtolower(sanitize_text_field($atts['symbol']));
    $api_url = "https://stooq.pl/q/l/?s={$symbol}&f=sd2t2ohlcv&h&e=json";
    get_db_data($symbol);
    $stock_data = get_stooq_data($api_url);

    if (!$stock_data) {
        return '<p>Nie udało się pobrać danych giełdowych.</p>';
    }

    add_stock_data($stock_data);
    ob_start();
    ?>
    <div class="stooq-widget">
        <h3>📈 Notowania: <?php echo esc_html($stock_data['symbol']); ?></h3>
        <p><strong>📅 Data:</strong> <?php echo esc_html($stock_data['date']); ?> |
            ⏰ <?php echo esc_html($stock_data['time']); ?></p>
        <p><strong>🟢 Otwarcie:</strong> <?php echo esc_html($stock_data['open']); ?> PLN</p>
        <p><strong>📈 Max:</strong> <?php echo esc_html($stock_data['high']); ?> PLN | 📉
            Min: <?php echo esc_html($stock_data['low']); ?> PLN</p>
        <p><strong>🔴 Zamknięcie:</strong> <?php echo esc_html($stock_data['close']); ?> PLN</p>
        <p><strong>📊 Wolumen:</strong> <?php echo number_format($stock_data['volume']); ?></p>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('stooq_stock', 'stooq_stock_shortcode');

// Pobieranie danych z API Stooq
function get_stooq_data($url): array
{
    $response = wp_remote_get($url);


    if (is_wp_error($response)) {
        return [];
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!isset($data['symbols'][0])) {
        return [];
    }
    return $data['symbols'][0];
}
