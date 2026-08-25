<?php
/**
 * ai-analyze.php — Improved local NLP fallback
 * ใช้เป็น fallback เมื่อ Claude API ไม่พร้อมใช้งาน
 */
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

$input = json_decode(file_get_contents("php://input"), true);
if (!$input || !isset($input['text'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Text required']);
    exit();
}

$text = trim($input['text']);
$lower = mb_strtolower($text, 'UTF-8');

// ── INCOME KEYWORDS ──────────────────────────────
$incomeKeywords = [
    'เงินเดือน' => 5, 'โบนัส' => 4, 'รับเงิน' => 3, 'โอนเข้า' => 3,
    'คืนเงิน' => 3, 'รายได้' => 4, 'ขายได้' => 3, 'ได้รับ' => 2,
    'เงินได้' => 3, 'กำไร' => 3, 'ผลตอบแทน' => 3, 'ดอกเบี้ย' => 4,
    'รับ' => 1, 'โอนมา' => 3, 'ได้เงิน' => 3, 'เบิกเงิน' => 2,
    'ถอนเงิน' => 2, 'เงินสดออก' => 2,
];

// ── EXPENSE KEYWORDS ─────────────────────────────
$expenseKeywords = [
    'ซื้อ' => 3, 'จ่าย' => 3, 'ค่า' => 2, 'เติม' => 2,
    'ช้อป' => 3, 'ใช้จ่าย' => 4, 'ชำระ' => 3, 'โอนให้' => 3,
    'บริจาค' => 4, 'ลงทุน' => 4, 'ฝาก' => 2, 'จ่ายบิล' => 4,
    'กิน' => 2, 'สั่ง' => 2, 'โอนเงิน' => 2,
];

// ── CATEGORY MATCHING ────────────────────────────
$categories = [
    'food'          => ['กิน','อาหาร','ข้าว','กาแฟ','น้ำ','ขนม','ร้านอาหาร','มื้อ','ชา','เบเกอรี่','ซูชิ','ส้มตำ'],
    'transport'     => ['น้ำมัน','แท็กซี่','grab','bolt','ค่ารถ','รถเมล์','mrt','bts','มอเตอร์ไซค์','เดินทาง','ขนส่ง','ค่าโดยสาร'],
    'shopping'      => ['ซื้อ','ช้อป','เสื้อผ้า','ของใช้','ห้างสรรพสินค้า','lazada','shopee','amazon','เครื่องสำอาง','รองเท้า'],
    'entertainment' => ['หนัง','เกม','เที่ยว','คอนเสิร์ต','บันเทิง','spotify','netflix','youtube premium','ดู','ท่องเที่ยว'],
    'bills'         => ['ค่าไฟ','ค่าน้ำ','ค่าเน็ต','ค่าโทรศัพท์','ประกัน','ค่าเช่า','อินเทอร์เน็ต','บิล','ค่าห้อง'],
    'health'        => ['หมอ','ยา','โรงพยาบาล','สุขภาพ','คลินิก','ป่วย','นวด','วิตามิน','ฟิตเนส','ออกกำลัง'],
    'education'     => ['เรียน','หนังสือ','คอร์ส','การศึกษา','โรงเรียน','มหาวิทยาลัย','ติวเตอร์','สัมมนา'],
    'salary'        => ['เงินเดือน','รายได้ประจำ','เงินเดือนออก'],
    'bonus'         => ['โบนัส','รางวัล','เงินพิเศษ'],
    'investment'    => ['ลงทุน','หุ้น','กองทุน','crypto','bitcoin','etf','พันธบัตร'],
];

$categoryNamesTH = [
    'food' => 'อาหาร', 'transport' => 'การเดินทาง', 'shopping' => 'ช้อปปิ้ง',
    'entertainment' => 'บันเทิง', 'bills' => 'ค่าบิล', 'health' => 'สุขภาพ',
    'education' => 'การศึกษา', 'salary' => 'เงินเดือน', 'bonus' => 'โบนัส',
    'investment' => 'การลงทุน', 'other' => 'อื่นๆ',
];

// ── EXTRACT AMOUNT ───────────────────────────────
preg_match_all('/(\d{1,3}(?:,\d{3})*(?:\.\d+)?|\d+(?:\.\d+)?)/u', $text, $matches);
$amount = 0;
if (!empty($matches[1])) {
    $amounts = array_map(fn($n) => floatval(str_replace(',', '', $n)), $matches[1]);
    $amount = max($amounts);
}

// ── SCORE TYPE ───────────────────────────────────
$incomeScore = 0;
$expenseScore = 0;

foreach ($incomeKeywords as $kw => $weight) {
    if (mb_strpos($lower, mb_strtolower($kw, 'UTF-8'), 0, 'UTF-8') !== false) {
        $incomeScore += $weight;
    }
}
foreach ($expenseKeywords as $kw => $weight) {
    if (mb_strpos($lower, mb_strtolower($kw, 'UTF-8'), 0, 'UTF-8') !== false) {
        $expenseScore += $weight;
    }
}

$type = $incomeScore > $expenseScore ? 'income' : 'expense';
$typeConfidence = abs($incomeScore - $expenseScore);

// ── DETECT CATEGORY ──────────────────────────────
$detectedCat = 'other';
$catScore = 0;
foreach ($categories as $cat => $keywords) {
    $score = 0;
    foreach ($keywords as $kw) {
        if (mb_strpos($lower, mb_strtolower($kw, 'UTF-8'), 0, 'UTF-8') !== false) {
            $score++;
        }
    }
    if ($score > $catScore) {
        $catScore = $score;
        $detectedCat = $cat;
    }
}

// ── CONFIDENCE SCORE ─────────────────────────────
$confidence = min(100, ($typeConfidence * 15) + ($catScore * 10) + ($amount > 0 ? 20 : 0));
$confidence = max(30, $confidence);

echo json_encode([
    'success' => true,
    'data' => [
        'type'        => $type,
        'amount'      => $amount,
        'description' => $text,
        'category'    => $detectedCat,
        'category_th' => $categoryNamesTH[$detectedCat] ?? 'อื่นๆ',
        'confidence'  => $confidence,
        'note'        => 'วิเคราะห์โดย Local NLP (Fallback)',
    ]
], JSON_UNESCAPED_UNICODE);
