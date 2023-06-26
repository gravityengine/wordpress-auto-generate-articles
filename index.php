<?php
/*
Plugin Name: OpenAI Article Auto Generator
Plugin URI: https://xunika.uk
Description: Generates text using OpenAI's gpt-3.5-turbo model
Version: 1.2
Author: xunika.uk
Author URI: https://xunika.uk
License: GPL2
*/

function openai_schedule_text_generation( $titles, $interval ) {
    $names = array(
        "Barack Obama",
        "George W. Bush",
        "Bill Clinton",
        "Ronald Reagan",
        "John F. Kennedy",
        "Richard Nixon",
        "Winston Churchill",
        "Mahatma Gandhi",
        "Martin Luther King Jr.",
        "Nelson Mandela",
        "Margaret Thatcher",
        "Albert Einstein",
        "Jane Austen",
        "Ernest Hemingway",
        "William Shakespeare",
        "Mark Twain",
        "Charles Dickens",
        "Friedrich Nietzsche",
        "Marie Curie",
        "Virginia Woolf",
        "Pablo Picasso",
        "Fidel Castro",
        "Sigmund Freud",
        "Dalai Lama",
        "Stephen Hawking",
        "Malcolm X",
        "Donald Trump", // 美国前总统，独特的政治语言风格
        "George Orwell", // 英国作家，1984和动物农场的作者，独特的政治寓言语言风格
        "J.K. Rowling", // 英国作家，哈利·波特系列的作者，奇幻文学语言风格
        "J.R.R. Tolkien", // 英国作家，魔戒系列的作者，史诗奇幻文学语言风格
        "Agatha Christie", // 英国侦探小说作家，神秘悬疑语言风格
        "Stephen King", // 美国恐怖小说作家，悬疑恐怖语言风格
        "F. Scott Fitzgerald", // 美国作家，了不起的盖茨比的作者，独特的现代主义语言风格
        "Harper Lee", // 美国作家，杀死一只知更鸟的作者，社会主义现实主义语言风格
        "Ray Bradbury" // 美国科幻小说作家，华氏451度的作者，科幻语言风格
    );
    // 随机选择一个名字
    $randomName = $names[array_rand($names)];

    // 创建一个新的模板，用随机选择的名字替换 "Trump"
    $prompt_template = sprintf('Do not write like an AI, instead, write a 2000 words article in %s\'s language style about: %%s', $randomName);
    $now = time();
    foreach ( $titles as $title ) {
        $prompt = sprintf( $prompt_template, $title );
        if ( ! wp_next_scheduled( 'openai_generate_text', array( $prompt, $title ) ) ) {
            wp_schedule_single_event( $now, 'openai_generate_text', array( $prompt, $title ) );
            $now += $interval;
        }
    }
}

function openai_do_generate_text( $prompt, $title ) {
    $openai_api_key = get_option( 'openai_api_key' );
    if ( ! $openai_api_key ) {
        error_log( 'Invalid OpenAI API key.' );
        return;
    }

    // Prepare the request data
    $data = array(
        'model' => 'gpt-3.5-turbo-16k',
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ]
    );
    // Convert the data to JSON
    $json_data = wp_json_encode( $data );

    // Send the request to OpenAI API
    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, "https://api.openai.com/v1/chat/completions" );
    curl_setopt( $ch, CURLOPT_POST, 1 );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $json_data );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openai_api_key
    ) );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    $response = curl_exec( $ch );
    $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    curl_close( $ch );

    // Check for errors
    if ( $http_code !== 200 ) {
        error_log( 'OpenAI API request failed with HTTP error: ' . $http_code );
        return;
    }

    // Parse the response
    $response_data = json_decode($response, true);
    $text = $response_data['choices'][0]['message']['content'];

    // Get the HTML code for the image
    $image_html = '<img src="https://source.unsplash.com/800x450/?'.urlencode($title).'">';

    // Append the image to the generated text
    $text = $image_html . "\n" . $text;
    
    $openai_post_date = get_option('openai_post_date');
    
    $unwanted_tags = ['the', 'a', 'an', 'in', 'on', 'at', 'and', 'or', 'but', 'of', 'for', 'with', 'as', 'by', 'to', 'is', 'it', 'that', 'this', 'be', 'are', 'from', 'has', 'have', 'was', 'were', 'will', 'would', 'can', 'could', 'does', 'do', 'did', 'not', 'so', 'up', 'out', 'if', 'about', 'who', 'what', 'where', 'when', 'which', 'how', 'why', 'then', 'than', 'them', 'they', 'their', 'there', 'been', 'because', 'into', 'over', 'under', 'since', 'until', 'upon', 'like', 'such', 'am', 'its'];

    $title = preg_replace("/[^\w\s]/", '', $title);
    $words = explode(' ', $title); // 将标题拆分为单词数组

    // 过滤掉不适合的标签
    $filtered_words = array_filter($words, function($word) use ($unwanted_tags) {
        return !in_array(strtolower($word), $unwanted_tags);
    });

    // 检查是否有合适的单词
    if (count($filtered_words) > 0) {
        $random_word = $filtered_words[array_rand($filtered_words)]; // 从过滤后的单词数组中随机选取一个单词
    } else {
        $random_word = "default"; // 如果没有合适的单词，将标签设为 "default"
    }

    $tags_input = $random_word;

    // Create a new post with the generated text
    $post_id = wp_insert_post( array(
        'post_title' => $title,
        'post_content' => $text,
        'post_status' => 'publish',
        'post_author' => '1', // Set the post author to "author"
        'tags_input' => $tags_input, // Use the words in the title as tags
        'post_category' => array(get_cat_ID($title)), // Set the category to the title
        'post_date' => $openai_post_date, // Set the post date
    ) );

    // Check for errors
    if ( is_wp_error( $post_id ) || $post_id === 0 ) {
        error_log( 'Failed to create new post.' );
        return;
    }

    // Log the generated text for debugging purposes
    error_log( 'Generated article: ' . $text );

    return array(
        'post_id' => $post_id,
        'title' => $title,
        'text' => $text,
    );
}

function openai_handle_text_generation( $prompt, $title ) {
    openai_do_generate_text( $prompt, $title );
}
add_action( 'openai_generate_text', 'openai_handle_text_generation', 10, 2 );

function openai_generate_titles( $num_titles ) {
    // Prepare the prompt
    $prompt = "Generate a random English article title";

    // Set up the API request
    $openai_api_key = get_option( 'openai_api_key' );
    if ( ! $openai_api_key ) {
        error_log( 'Invalid OpenAI API key.' );
        return;
    }

    $data = array(
        'model' => 'gpt-3.5-turbo-16k',
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ]
    );

    $json_data = wp_json_encode( $data );

    // Send the request to OpenAI API
    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, "https://api.openai.com/v1/chat/completions" );
    curl_setopt( $ch, CURLOPT_POST, 1 );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $json_data );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openai_api_key
    ) );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    $response = curl_exec( $ch );
    $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    curl_close( $ch );

    // Check for errors
    if ( $http_code !== 200 ) {
        error_log( 'OpenAI API request failed with HTTP error: ' . $http_code );
        return;
    }

    // Parse the response
    $response_data = json_decode( $response, true );
    $text = $response_data['choices'][0]['message']['content'];

    // Generate the specified number of titles
    $titles = array();

    for ( $i = 0; $i < $num_titles; $i++ ) {
        $titles[] = $text;
    }

    return $titles;
}

function openai_settings_page() {
    if ( isset( $_POST['generate_articles'] ) ) {
        $num_titles = intval( $_POST['openai_num_titles'] );
        $titles = openai_generate_titles( $num_titles );
        $interval = intval( $_POST['openai_interval'] );
        openai_schedule_text_generation( $titles, $interval );
        echo '<div class="notice notice-success"><p>Article generation scheduled successfully!</p></div>';
    }

    if ( isset( $_POST['openai_api_key'] ) ) {
        $api_key = sanitize_text_field( $_POST['openai_api_key'] );
        if ( $api_key === get_option( 'openai_api_key' ) ) {
            // The API Key is already saved, no need to show the success message.
        } else {
            update_option( 'openai_api_key', $api_key );
            echo '<div class="notice notice-success"><p>API key saved successfully!</p></div>';
        }
    }
    if ( isset( $_POST['openai_post_date'] ) ) {
    $post_date = sanitize_text_field( $_POST['openai_post_date'] );
    if ( $post_date !== get_option( 'openai_post_date' ) ) {
        update_option( 'openai_post_date', $post_date );
    }
}
    if (isset($_POST['view_schedule'])) {
        openai_view_schedule();
        return;
    }
?>
    <div class="wrap">
        <h1>Settings</h1>
        <form method="post" action="">
            <label for="openai_num_titles">Number of articles to generate:</label>
            <input type="number" name="openai_num_titles" min="1" required>
            <label for="openai_api_key">API Key:</label>
            <input type="text" name="openai_api_key" id="openai_api_key" value="<?php echo esc_attr( get_option( 'openai_api_key' ) ); ?>"><br>
            <label for="openai_interval">Interval (seconds):</label>
            <input type="number" name="openai_interval" min="1" value="<?php echo esc_attr( get_option( 'openai_interval', 600 ) ); ?>">
            <p class="description">Enter the interval time (in seconds) between each article generation.</p>
            <input type="datetime-local" id="openai_post_date" name="openai_post_date" value="<?php echo esc_attr( get_option('openai_post_date') ); ?>" />
            <input type="submit" name="generate_articles" class="button button-primary" value="Generate Articles">
        </form>
        <input type="submit" name="view_schedule" class="button" value="View Schedule">
    </div>
    <?php
}

function openai_view_schedule() {
    // 获取所有已安排的事件
    $scheduled_events = _get_cron_array();
    $plugin_callback = 'openai_generate_text'; // 你的插件回调函数名称

    ?>
    <div class="wrap">
        <h1>Scheduled Events</h1>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Callback</th>
                    <th>Next Run</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($scheduled_events as $timestamp => $cronhooks) : ?>
                    <?php foreach ($cronhooks as $cronhook => $cronhook_data) : ?>
                        <?php if ($cronhook === $plugin_callback) : ?>
                            <?php foreach ($cronhook_data as $key => $schedule) : ?>
                                <?php
                                $next_run = $timestamp;
                                if (isset($schedule['interval'])) {
                                    $next_run += $schedule['interval'];
                                }
                                $next_run_formatted = date('Y-m-d H:i:s', $next_run);
                                ?>
                                <tr>
                                    <td><?php echo $cronhook; ?></td>
                                    <td><?php echo $next_run_formatted; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function openai_settings_link( $links ) {
    $settings_link = '<a href="options-general.php?page=openai-settings">Settings</a>';
    array_push( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'openai_settings_link' );

function openai_settings_menu() {
    add_options_page( 'Article Auto Generator', 'Article Auto Generator', 'manage_options', 'openai-settings', 'openai_settings_page' );
}
add_action( 'admin_menu', 'openai_settings_menu' );
