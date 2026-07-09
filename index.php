<?php
// index.php - نسخه با مدیریت کانال‌های خاص
// ============================================

$token = "7835317118:AAGNcEM-_SnIU0ie-QdWPf1dkvtdLHVBAUs";
$admin_id = 1033416576;
$bot_url = "https://api.telegram.org/bot$token";
$data_dir = "data_php";

if (!is_dir($data_dir)) { mkdir($data_dir, 0777, true); }

function db_get($db, $column) {
    global $data_dir;
    $path = "$data_dir/$db/$column.txt";
    if (file_exists($path)) return trim(file_get_contents($path));
    return false;
}

function db_set($db, $column, $data) {
    global $data_dir;
    $path = "$data_dir/$db";
    if (!is_dir($path)) mkdir($path, 0777, true);
    return file_put_contents("$path/$column.txt", $data) !== false;
}

function sendMessage($chat_id, $text, $keyboard = null) {
    global $bot_url;
    $params = [
        'chat_id' => $chat_id,
        'text' => $text,
        'disable_web_page_preview' => false,
        'parse_mode' => 'HTML'
    ];
    if ($keyboard !== null) {
        $params['reply_markup'] = json_encode([
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ]);
    }
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $bot_url . "/sendMessage",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $http_code == 200;
}

$mainMenu = [
    ["📡 تنظیم کانال اصلی", "📋 نمایش کانال‌ها"],
    ["➕ افزودن کانال", "➖ حذف کانال"],
    ["📌 کانال‌های خاص", "⚙️ تنظیمات"],
    ["📊 وضعیت"]
];

$specialMenu = [
    ["➕ افزودن کانال خاص", "➖ حذف کانال خاص"],
    ["📋 نمایش کانال‌های خاص", "🔙 بازگشت"]
];

$settingsMenu = [
    ["🔢 تعداد در هر پیام", "📝 قالب نام"],
    ["💬 نقل قول", "📄 متن آغازین"],
    ["📄 متن پایانی", "🔗 لینک بالای کپشن"],
    ["🔐 رمزنگاری Base64", "⏱ زمان کرون"],
    ["⏱ تأخیر ارسال (دقیقه)", "📡 تست TCP"],
    ["🔙 بازگشت"]
];

$backMenu = [["🔙 بازگشت"]];

$update = file_get_contents("php://input");
if (empty($update)) { http_response_code(200); exit; }
$data = json_decode($update, true);
$chat_id = null;
$text = null;

if (isset($data["message"]["text"])) {
    $chat_id = $data["message"]["chat"]["id"];
    $text = $data["message"]["text"];
} elseif (isset($data["callback_query"])) {
    $chat_id = $data["callback_query"]["message"]["chat"]["id"];
    $text = $data["callback_query"]["data"];
} else {
    http_response_code(200);
    exit;
}

if ((string)$chat_id !== (string)$admin_id) {
    sendMessage($chat_id, "⛔ دسترسی محدود", null);
    http_response_code(200);
    exit;
}

$state = db_get($chat_id, "state") ?: "";

switch ($text) {
    case "/start":
        db_set($chat_id, "state", "");
        sendMessage($chat_id, "🤖 <b>ربات مدیریت کانفیگ v5.0</b>\n\n✅ کانال‌های خاص (بدون تست TCP)\n✅ ارسال دقیق پست‌ها با تغییر اسم", $mainMenu);
        break;

    case "📌 کانال‌های خاص":
        db_set($chat_id, "state", "");
        sendMessage($chat_id, "📌 <b>مدیریت کانال‌های خاص</b>\n\nکانال‌های خاص بدون تست TCP و بدون محدودیت تعداد ارسال می‌شوند.\nمحتوای پست دقیقاً با تغییر اسم کانفیگ‌ها ارسال می‌شود.", $specialMenu);
        break;

    case "➕ افزودن کانال خاص":
        db_set($chat_id, "state", "wait_add_special");
        sendMessage($chat_id, "➕ آیدی کانال خاص را وارد کنید:\n\n<i>مثال: @MySpecialChannel</i>", $backMenu);
        break;

    case "➖ حذف کانال خاص":
        $special_channels = json_decode(db_get("", "special_channels") ?: "[]", true);
        if (empty($special_channels)) {
            sendMessage($chat_id, "⚠️ هیچ کانال خاصی ثبت نشده است.", $specialMenu);
            db_set($chat_id, "state", "");
        } else {
            db_set($chat_id, "state", "wait_remove_special");
            $msg = "<b>کانال‌های خاص فعلی:</b>\n";
            foreach ($special_channels as $ch) {
                $msg .= "• @$ch\n";
            }
            $msg .= "\nآیدی کانال مورد نظر را وارد کنید:";
            sendMessage($chat_id, $msg, $backMenu);
        }
        break;

    case "📋 نمایش کانال‌های خاص":
        $special_channels = json_decode(db_get("", "special_channels") ?: "[]", true);
        if (empty($special_channels)) {
            sendMessage($chat_id, "⚠️ هیچ کانال خاصی ثبت نشده است.", $specialMenu);
        } else {
            $msg = "<b>📋 کانال‌های خاص:</b>\n\n";
            foreach ($special_channels as $i => $ch) {
                $msg .= ($i + 1) . ". @$ch\n";
            }
            sendMessage($chat_id, $msg, $specialMenu);
        }
        db_set($chat_id, "state", "");
        break;

    case "📡 تنظیم کانال اصلی":
        db_set($chat_id, "state", "wait_main_channel");
        sendMessage($chat_id, "📡 آیدی کانال اصلی را وارد کنید:\n\n<i>مثال: @MyChannel</i>", $backMenu);
        break;

    case "📋 نمایش کانال‌ها":
        $channels = json_decode(db_get("", "نمایش کانال ها") ?: "[]", true);
        if (empty($channels)) {
            sendMessage($chat_id, "⚠️ هیچ کانالی ثبت نشده است.", $mainMenu);
        } else {
            $msg = "<b>📋 کانال‌های منبع:</b>\n\n";
            foreach ($channels as $i => $ch) {
                $msg .= ($i + 1) . ". @$ch\n";
            }
            sendMessage($chat_id, $msg, $mainMenu);
        }
        db_set($chat_id, "state", "");
        break;

    case "➕ افزودن کانال":
        db_set($chat_id, "state", "wait_add_channel");
        sendMessage($chat_id, "➕ آیدی کانال منبع را وارد کنید:\n\n<i>مثال: @ConfigsHUB2</i>", $backMenu);
        break;

    case "➖ حذف کانال":
        $channels = json_decode(db_get("", "نمایش کانال ها") ?: "[]", true);
        if (empty($channels)) {
            sendMessage($chat_id, "⚠️ هیچ کانالی برای حذف وجود ندارد.", $mainMenu);
            db_set($chat_id, "state", "");
        } else {
            db_set($chat_id, "state", "wait_remove_channel");
            $msg = "<b>کانال‌های فعلی:</b>\n";
            foreach ($channels as $ch) {
                $msg .= "• @$ch\n";
            }
            $msg .= "\nآیدی کانال مورد نظر را وارد کنید:";
            sendMessage($chat_id, $msg, $backMenu);
        }
        break;

    case "⚙️ تنظیمات":
        db_set($chat_id, "state", "");
        sendMessage($chat_id, "⚙️ <b>تنظیمات ربات</b>\n\nیک گزینه را انتخاب کنید:", $settingsMenu);
        break;

    case "📊 وضعیت":
        db_set($chat_id, "state", "");
        $main_ch = db_get("", "تنظیم کانال اصلی") ?: "❌ تنظیم نشده";
        $count = db_get("", "تعداد کانفیگ در هر پیام") ?: "2";
        $template = db_get("", "قالب نام کانفیگ") ?: "config_{number}";
        $quote = (db_get("", "استفاده از نقل قول") === "True") ? "✅" : "❌";
        $enc = (db_get("", "استفاده از رمزنگاری") === "True") ? "✅ (یکپارچه)" : "❌";
        $header = db_get("", "متن آغازین") ?: "خالی";
        $footer = db_get("", "متن پایانی") ?: "خالی";
        $link_caption = db_get("", "لینک بالای کپشن") ?: "خالی";
        $cron = db_get("", "زمان کرون جاب") ?: "6";
        $delay = db_get("", "تاخیر ارسال (دقیقه)") ?: "0";
        $tcp_test = (db_get("", "فعال/غیرفعال تست TCP") !== "False") ? "✅" : "❌";
        $sent = json_decode(db_get("", "کانفیگ‌های ارسال شده") ?: "[]", true);
        $channels = json_decode(db_get("", "نمایش کانال ها") ?: "[]", true);
        $special_channels = json_decode(db_get("", "special_channels") ?: "[]", true);
        $last_sent = (int)(db_get("", "آخرین زمان ارسال") ?: 0);
        $next_sent = $last_sent ? date('Y-m-d H:i:s', $last_sent + (int)$cron) : "نامشخص";

        $status = "<b>📊 وضعیت ربات</b>\n\n" .
                  "📡 کانال اصلی: $main_ch\n" .
                  "📋 کانال‌های منبع: " . count($channels) . " عدد\n" .
                  "📌 کانال‌های خاص: " . count($special_channels) . " عدد\n" .
                  "📦 ارسال شده: " . count($sent) . " کانفیگ\n" .
                  "⏱ ارسال بعدی: $next_sent\n\n" .
                  "<b>⚙️ تنظیمات:</b>\n" .
                  "• تعداد: $count (حداکثر ۲۰)\n" .
                  "• قالب: $template\n" .
                  "• نقل قول: $quote\n" .
                  "• رمزنگاری: $enc\n" .
                  "• آغازین: $header\n" .
                  "• پایانی: $footer\n" .
                  "• لینک بالای کپشن: $link_caption\n" .
                  "• زمان کرون: {$cron}s\n" .
                  "• تأخیر ارسال: {$delay} دقیقه (0 = فوری)\n" .
                  "• تست TCP: $tcp_test";

        sendMessage($chat_id, $status, $mainMenu);
        break;

    case "🔢 تعداد در هر پیام":
        db_set($chat_id, "state", "wait_count");
        $current = db_get("", "تعداد کانفیگ در هر پیام") ?: "2";
        sendMessage($chat_id, "🔢 تعداد فعلی: <b>$current</b>\n\n📊 محدوده مجاز: <b>۱ تا ۲۰</b>\n\nلطفاً عدد جدید را وارد کنید:", $backMenu);
        break;

    case "📝 قالب نام":
        db_set($chat_id, "state", "wait_template");
        $current = db_get("", "قالب نام کانفیگ") ?: "config_{number}";
        sendMessage($chat_id, "📝 قالب فعلی: <b>$current</b>\n\nمتغیر: <code>{number}</code> با شماره جایگزین می‌شود\n\nقالب جدید را وارد کنید:", $backMenu);
        break;

    case "💬 نقل قول":
        $cur = (db_get("", "استفاده از نقل قول") === "True");
        $new = !$cur;
        db_set("", "استفاده از نقل قول", $new ? "True" : "False");
        sendMessage($chat_id, "✅ نقل قول " . ($new ? "<b>فعال</b>" : "<b>غیرفعال</b>") . " شد.", $settingsMenu);
        break;

    case "📄 متن آغازین":
        db_set($chat_id, "state", "wait_header");
        $current = db_get("", "متن آغازین") ?: "خالی";
        sendMessage($chat_id, "📄 متن آغازین فعلی:\n<i>$current</i>\n\nمتن جدید را وارد کنید (یا 'حذف' برای پاک کردن):", $backMenu);
        break;

    case "📄 متن پایانی":
        db_set($chat_id, "state", "wait_footer");
        $current = db_get("", "متن پایانی") ?: "خالی";
        sendMessage($chat_id, "📄 متن پایانی فعلی:\n<i>$current</i>\n\nمتن جدید را وارد کنید (یا 'حذف'):", $backMenu);
        break;

    case "🔗 لینک بالای کپشن":
        db_set($chat_id, "state", "wait_link_caption_text");
        $current = db_get("", "لینک بالای کپشن") ?: "خالی";
        sendMessage($chat_id, "🔗 لینک بالای کپشن فعلی:\n<i>$current</i>\n\n<b>مرحله ۱:</b>\nمتن نمایشی لینک را وارد کنید (مثلاً \"کانال ما\"):", $backMenu);
        break;

    case "🔐 رمزنگاری Base64":
        $cur = (db_get("", "استفاده از رمزنگاری") === "True");
        $new = !$cur;
        db_set("", "استفاده از رمزنگاری", $new ? "True" : "False");
        $msg = "✅ رمزنگاری Base64 " . ($new ? "<b>فعال 🔐</b>" : "<b>غیرفعال ❌</b>") . " شد.";
        if ($new) {
            $msg .= "\n\n📝 <b>حالت رمزنگاری یکپارچه:</b>\n• تمام کانفیگ‌ها در یک بلوک Base64\n• همراه با نام‌های مشخص شده";
        } else {
            $msg .= "\n\n📝 <b>حالت عادی:</b>\n• همه کانفیگ‌ها با هم ارسال می‌شوند";
        }
        sendMessage($chat_id, $msg, $settingsMenu);
        break;

    case "⏱ زمان کرون":
        db_set($chat_id, "state", "wait_cron");
        $current = db_get("", "زمان کرون جاب") ?: "6";
        sendMessage($chat_id, "⏱ زمان فعلی: <b>$current</b> ثانیه\n\n📊 حداقل: <b>۳ ثانیه</b>\n\nلطفاً زمان جدید را وارد کنید:", $backMenu);
        break;

    case "⏱ تأخیر ارسال (دقیقه)":
        db_set($chat_id, "state", "wait_delay");
        $current = db_get("", "تاخیر ارسال (دقیقه)") ?: "0";
        sendMessage($chat_id, "⏱ تأخیر فعلی: <b>$current</b> دقیقه\n\n📊 محدوده: <b>۰ تا ۶۰</b> (۰ = فوری)\n\nلطفاً تأخیر جدید را وارد کنید:", $backMenu);
        break;

    case "📡 تست TCP":
        $cur = (db_get("", "فعال/غیرفعال تست TCP") !== "False");
        $new = !$cur;
        db_set("", "فعال/غیرفعال تست TCP", $new ? "True" : "False");
        sendMessage($chat_id, "✅ تست TCP " . ($new ? "<b>فعال</b>" : "<b>غیرفعال</b>") . " شد.\n\n📌 در حالت فعال، کانفیگ‌هایی که پورت باز نداشته باشند ارسال نمی‌شوند.", $settingsMenu);
        break;

    case "🔙 بازگشت":
        db_set($chat_id, "state", "");
        sendMessage($chat_id, "🔙 بازگشت به منوی اصلی", $mainMenu);
        break;

    default:
        switch ($state) {
            case "wait_main_channel":
                $ch = trim(str_replace("@", "", $text));
                if (!empty($ch)) {
                    db_set("", "تنظیم کانال اصلی", "@$ch");
                    db_set($chat_id, "state", "");
                    sendMessage($chat_id, "✅ کانال اصلی با موفقیت تنظیم شد:\n<b>@$ch</b>", $mainMenu);
                } else {
                    sendMessage($chat_id, "❌ آیدی نامعتبر است.", $backMenu);
                }
                break;

            case "wait_add_channel":
                $ch = trim(str_replace("@", "", $text));
                if (empty($ch)) break;
                $channels = json_decode(db_get("", "نمایش کانال ها") ?: "[]", true);
                if (!in_array($ch, $channels)) {
                    $channels[] = $ch;
                    db_set("", "نمایش کانال ها", json_encode($channels));
                    db_set($chat_id, "state", "");
                    sendMessage($chat_id, "✅ کانال <b>@$ch</b> افزوده شد.", $mainMenu);
                } else {
                    sendMessage($chat_id, "⚠️ این کانال قبلاً اضافه شده است.", $backMenu);
                }
                break;

            case "wait_remove_channel":
                $ch = trim(str_replace("@", "", $text));
                $channels = json_decode(db_get("", "نمایش کانال ها") ?: "[]", true);
                if (in_array($ch, $channels)) {
                    $channels = array_values(array_diff($channels, [$ch]));
                    db_set("", "نمایش کانال ها", json_encode($channels));
                    db_set($chat_id, "state", "");
                    sendMessage($chat_id, "✅ کانال <b>@$ch</b> حذف شد.", $mainMenu);
                } else {
                    sendMessage($chat_id, "⚠️ این کانال در لیست وجود ندارد.", $backMenu);
                }
                break;

            case "wait_add_special":
                $ch = trim(str_replace("@", "", $text));
                if (!empty($ch)) {
                    $special_channels = json_decode(db_get("", "special_channels") ?: "[]", true);
                    if (!in_array($ch, $special_channels)) {
                        $special_channels[] = $ch;
                        db_set("", "special_channels", json_encode($special_channels));
                        db_set($chat_id, "state", "");
                        sendMessage($chat_id, "✅ کانال خاص <b>@$ch</b> افزوده شد.", $mainMenu);
                    } else {
                        sendMessage($chat_id, "⚠️ این کانال قبلاً در لیست خاص است.", $backMenu);
                    }
                } else {
                    sendMessage($chat_id, "❌ آیدی نامعتبر است.", $backMenu);
                }
                break;

            case "wait_remove_special":
                $ch = trim(str_replace("@", "", $text));
                $special_channels = json_decode(db_get("", "special_channels") ?: "[]", true);
                if (in_array($ch, $special_channels)) {
                    $special_channels = array_values(array_diff($special_channels, [$ch]));
                    db_set("", "special_channels", json_encode($special_channels));
                    db_set($chat_id, "state", "");
                    sendMessage($chat_id, "✅ کانال خاص <b>@$ch</b> حذف شد.", $mainMenu);
                } else {
                    sendMessage($chat_id, "⚠️ این کانال در لیست خاص وجود ندارد.", $backMenu);
                }
                break;

            case "wait_count":
                if (is_numeric($text) && $text >= 1 && $text <= 20) {
                    db_set("", "تعداد کانفیگ در هر پیام", (int)$text);
                    db_set($chat_id, "state", "");
                    sendMessage($chat_id, "✅ تعداد به <b>$text</b> تغییر یافت.", $mainMenu);
                } else {
                    sendMessage($chat_id, "❌ لطفاً عددی بین ۱ تا ۲۰ وارد کنید.", $backMenu);
                }
                break;

            case "wait_template":
                $t = trim($text);
                if (!empty($t)) {
                    db_set("", "قالب نام کانفیگ", $t);
                    db_set($chat_id, "state", "");
                    sendMessage($chat_id, "✅ قالب نام به <b>$t</b> تغییر یافت.", $mainMenu);
                } else {
                    sendMessage($chat_id, "❌ قالب نمی‌تواند خالی باشد.", $backMenu);
                }
                break;

            case "wait_header":
                db_set($chat_id, "state", "");
                if (in_array(mb_strtolower($text), ['حذف', 'خالی', 'null', 'none'])) {
                    db_set("", "متن آغازین", "");
                    sendMessage($chat_id, "✅ متن آغازین حذف شد.", $mainMenu);
                } else {
                    db_set("", "متن آغازین", $text);
                    sendMessage($chat_id, "✅ متن آغازین تنظیم شد.", $mainMenu);
                }
                break;

            case "wait_footer":
                db_set($chat_id, "state", "");
                if (in_array(mb_strtolower($text), ['حذف', 'خالی', 'null', 'none'])) {
                    db_set("", "متن پایانی", "");
                    sendMessage($chat_id, "✅ متن پایانی حذف شد.", $mainMenu);
                } else {
                    db_set("", "متن پایانی", $text);
                    sendMessage($chat_id, "✅ متن پایانی تنظیم شد.", $mainMenu);
                }
                break;

            case "wait_link_caption_text":
                if (in_array(mb_strtolower($text), ['حذف', 'خالی', 'null', 'none'])) {
                    db_set("", "لینک بالای کپشن", "");
                    db_set($chat_id, "state", "");
                    sendMessage($chat_id, "✅ لینک بالای کپشن حذف شد.", $mainMenu);
                    break;
                }
                $link_text = trim($text);
                if (empty($link_text)) break;
                db_set($chat_id, "link_text_temp", $link_text);
                db_set($chat_id, "state", "wait_link_caption_url");
                sendMessage($chat_id, "🔗 <b>مرحله ۲:</b>\nلینک (URL) را وارد کنید:\n\n<i>مثال: https://t.me/MyChannel</i>\n\n✅ متن شما: <b>$link_text</b>", $backMenu);
                break;

            case "wait_link_caption_url":
                $url = trim($text);
                if (empty($url)) break;
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    if (preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $url)) {
                        $url = "https://" . $url;
                    } else {
                        sendMessage($chat_id, "❌ لینک نامعتبر است.", $backMenu);
                        break;
                    }
                }
                $link_text = db_get($chat_id, "link_text_temp");
                $final_link = "<a href=\"$url\">$link_text</a>";
                db_set("", "لینک بالای کپشن", $final_link);
                db_set($chat_id, "state", "");
                db_set($chat_id, "link_text_temp", "");
                sendMessage($chat_id, "✅ لینک بالای کپشن تنظیم شد:\n\n<b>$final_link</b>", $mainMenu);
                break;

            case "wait_cron":
                if (is_numeric($text) && $text >= 3) {
                    db_set("", "زمان کرون جاب", (int)$text);
                    db_set($chat_id, "state", "");
                    sendMessage($chat_id, "✅ زمان کرون به <b>$text</b> ثانیه تغییر یافت.", $mainMenu);
                } else {
                    sendMessage($chat_id, "❌ لطفاً عدد ۳ یا بیشتر وارد کنید.", $backMenu);
                }
                break;

            case "wait_delay":
                if (is_numeric($text) && $text >= 0 && $text <= 60) {
                    db_set("", "تاخیر ارسال (دقیقه)", (int)$text);
                    db_set($chat_id, "state", "");
                    sendMessage($chat_id, "✅ تأخیر ارسال به <b>$text</b> دقیقه تغییر یافت.", $mainMenu);
                } else {
                    sendMessage($chat_id, "❌ لطفاً عددی بین ۰ تا ۶۰ وارد کنید.", $backMenu);
                }
                break;

            default:
                db_set($chat_id, "state", "");
                sendMessage($chat_id, "👈 لطفاً از منو انتخاب کنید.", $mainMenu);
                break;
        }
        break;
}

http_response_code(200);
?>