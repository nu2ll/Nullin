<?php

final class Tools
{
    private const SETS = [
        'U' => 'ABCDEFGHJKLMNPQRSTUVWXYZ',
        'L' => 'abcdefghijkmnopqrstuvwxyz',
        'D' => '23456789',
        'S' => '!@#$%^&*-_=+?',
    ];

    public static function password(int $length): string
    {
        $length = max(6, min($length, 64));
        $all    = implode('', self::SETS);
        $chars  = [];
        foreach (self::SETS as $set) {
            $chars[] = $set[random_int(0, strlen($set) - 1)];
        }
        while (count($chars) < $length) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }
        return implode('', $chars);
    }

    public static function passwordText(int $length): string
    {
        return L("<b>🔑 پسورد ساز</b>\n\n<code>", "<b>🔑 Password maker</b>\n\n<code>") . h(self::password($length)) . "</code>\n\n"
            . L('📏 طول: ', '📏 Length: ') . fa($length) . L(' · 💪 قدرت: خیلی قوی', ' · 💪 Strength: very strong') . L("\n<i>روی پسورد بزن تا کپی بشه</i>", "\n<i>Tap the password to copy it</i>");
    }

    public static function passwordKeyboard(): array
    {
        $row = [];
        foreach ([8, 12, 16, 24, 32] as $n) {
            $row[] = btn(fa($n), "pass:{$n}");
        }
        return ikb([$row]);
    }

    public static function coinKeyboard(): array
    {
        return ikb([[btn(L('🪙 بنداز!', '🪙 Flip!'), 'coin:flip', 'success')]]);
    }

    public static function coinResult(): string
    {
        $r = random_int(0, 1) ? L('🦁 <b>شیر</b>', '🦁 <b>Heads</b>') : L('➖ <b>خط</b>', '➖ <b>Tails</b>');
        return L("<b>🪙 سکه</b>\n\nسکه چرخید و چرخید و... {$r} اومد!", "<b>🪙 Coin</b>\n\nThe coin spun and spun and... {$r}!");
    }

    public static function hafezUrl(int $n = 0): string
    {
        return 'https://api.codebazan.ir/fal/hafez/' . ($n > 0 ? $n : random_int(1, 149)) . '.gif';
    }

    public static function hafezPhoto(): ?string
    {
        $n  = (string) random_int(1, 149);
        $id = Store::get('hafez', $n);
        if (is_string($id) && $id !== '') {
            return $id;
        }
        $r = Http::request('GET', self::hafezUrl((int) $n), ['timeout' => 20]);
        if ($r['code'] !== 200 || $r['body'] === '') {
            return null;
        }
        $in  = (string) tempnam(sys_get_temp_dir(), 'nl_hf_');
        $out = $in . '.jpg';
        file_put_contents($in, $r['body']);
        @exec('timeout 20 ffmpeg -y -v error -i ' . escapeshellarg($in) . ' -frames:v 1 ' . escapeshellarg($out) . ' 2>/dev/null', $o, $rc);
        $bytes = is_file($out) ? file_get_contents($out) : false;
        @unlink($in);
        @unlink($out);
        if ($bytes === false || $bytes === '') {
            return null;
        }
        $id = ImageGen::fileId($bytes);
        if ($id !== null) {
            Store::set('hafez', $n, $id);
        }
        return $id;
    }

    public static function hafezCaption(): string
    {
        return L('🔮 <b>فال حافظ شما</b>', '🔮 <b>Your Hafez fortune</b>') . L("\n<i>نیت کن، بعد بخون...</i>", "\n<i>Make a wish, then read...</i>");
    }

    public static function hafezKeyboard(): array
    {
        return ikb([[btn(L('🔄 یه فال دیگه', '🔄 Another fortune'), 'fal:refresh')]]);
    }

    public static function showHafez(string $imid): ?array
    {
        return Rich::editInline($imid, self::hafezCaption(), self::hafezKeyboard(), self::hafezPhoto());
    }

    public static function normalizeNid(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', en($raw)) ?? '';
        if ($digits === '' || strlen($digits) > 10 || strlen($digits) < 8) {
            return null;
        }
        return str_pad($digits, 10, '0', STR_PAD_LEFT);
    }

    public static function isValidNid(string $code): bool
    {
        if (!preg_match('/^\d{10}$/', $code) || preg_match('/^(\d)\1{9}$/', $code)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $code[$i] * (10 - $i);
        }
        $rem   = $sum % 11;
        $check = (int) $code[9];
        return $rem < 2 ? $check === $rem : $check === 11 - $rem;
    }

    public static function nidDisplay(string $code): string
    {
        return "\u{2066}" . substr($code, 0, 3) . '-' . substr($code, 3, 6) . '-' . $code[9] . "\u{2069}";
    }

    public static function nidText(string $code): string
    {
        $ok = self::isValidNid($code);
        return L("<b>🪪 بررسی کد ملی</b>\n\n", "<b>🪪 National ID check</b>\n\n")
            . L('کد: <code>', 'Code: <code>') . self::nidDisplay($code) . "</code>\n"
            . ($ok ? L('✅ ساختار این کد <b>درسته</b>.', '✅ The structure of this code is <b>valid</b>.') : L('❌ ساختار این کد <b>اشتباهه</b>.', '❌ The structure of this code is <b>wrong</b>.')) . "\n\n"
            . L("<i>⚠️ فقط رقم کنترل بررسی شده،\nنه ثبت واقعی در ثبت‌احوال.</i>", "<i>⚠️ Only the check digit was verified,\nnot the actual civil registry.</i>");
    }

    private const CARD_BINS = [
        '603799' => ['Bank Melli Iran', 'بانک ملی ایران'],
        '589210' => ['Bank Sepah', 'بانک سپه'],
        '627648' => ["Tose'e Saderat Bank", 'بانک توسعه صادرات'],
        '627961' => ['Sanat o Madan Bank', 'بانک صنعت و معدن'],
        '603770' => ['Bank Keshavarzi', 'بانک کشاورزی'],
        '628023' => ['Bank Maskan', 'بانک مسکن'],
        '627353' => ['Bank Tejarat', 'بانک تجارت'],
        '585983' => ['Bank Tejarat', 'بانک تجارت'],
        '603769' => ['Bank Saderat Iran', 'بانک صادرات ایران'],
        '610433' => ['Bank Mellat', 'بانک ملت'],
        '991975' => ['Bank Mellat', 'بانک ملت'],
        '589463' => ['Bank Refah Kargaran', 'بانک رفاه کارگران'],
        '627760' => ['Post Bank', 'پست بانک'],
        '502908' => ["Tose'e Ta'avon Bank", 'بانک توسعه تعاون'],
        '627412' => ['Bank Eghtesad Novin', 'بانک اقتصاد نوین'],
        '622106' => ['Bank Parsian', 'بانک پارسیان'],
        '639194' => ['Bank Parsian', 'بانک پارسیان'],
        '621986' => ['Bank Saman', 'بانک سامان'],
        '502229' => ['Bank Pasargad', 'بانک پاسارگاد'],
        '639347' => ['Bank Pasargad', 'بانک پاسارگاد'],
        '639607' => ['Bank Sarmayeh', 'بانک سرمایه'],
        '639346' => ['Bank Sina', 'بانک سینا'],
        '606373' => ['Mehr Iran Credit Institution', 'موسسه قرض الحسنه مهر ایران'],
        '502806' => ['Bank Shahr', 'بانک شهر'],
        '504706' => ['Bank Shahr', 'بانک شهر'],
        '636214' => ['Bank Ayandeh', 'بانک آینده'],
        '627381' => ['Bank Ansar', 'بانک انصار'],
        '505416' => ['Bank Gardeshgari', 'بانک گردشگری'],
        '636949' => ['Hekmat Iranian Bank', 'بانک حکمت ایرانیان'],
        '502938' => ['Bank Day', 'بانک دی'],
        '505785' => ['Iran Zamin Bank', 'بانک ایران زمین'],
        '504172' => ['Bank Resalat', 'بانک قرض الحسنه رسالت'],
        '505801' => ['Kowsar Credit Institution', 'موسسه اعتباری کوثر'],
        '627488' => ['Bank Karafarin', 'بانک کارآفرین'],
        '502910' => ['Caspian Credit Institution', 'موسسه اعتباری کاسپین'],
        '585947' => ['Khavarmianeh Bank', 'بانک خاورمیانه'],
        '639599' => ['Ghavamin Bank', 'بانک قوامین'],
        '170019' => ['Bank Melli Iran', 'بانک ملی ایران'],
        '207177' => ["Tose'e Saderat Bank", 'بانک توسعه صادرات'],
        '639217' => ['Bank Keshavarzi', 'بانک کشاورزی'],
        '627884' => ['Bank Parsian', 'بانک پارسیان'],
        '505426' => ['Bank Gardeshgari', 'بانک گردشگری'],
        '636795' => ['Central Bank of Iran', 'بانک مرکزی'],
        '636797' => ['Central Bank of Iran', 'بانک مرکزی'],
        '903769' => ['Bank Saderat Iran', 'بانک صادرات ایران'],
        '639370' => ['Mehr Eghtesad Bank', 'بانک مهر اقتصاد'],
        '628157' => ["Tose'e Credit Institution", 'موسسه اعتباری توسعه'],
        '507677' => ['Noor Credit Institution', 'موسسه اعتباری نور'],
        '606256' => ['Mellal Credit Institution', 'موسسه اعتباری ملل'],
        '581874' => ['Iran Venezuela Bi-National Bank', 'بانک ایران ونزوئلا'],
    ];

    private const SHEBA_CODES = [
        '010' => ['Central Bank of Iran', 'بانک مرکزی'],
        '011' => ['Sanat o Madan Bank', 'بانک صنعت و معدن'],
        '012' => ['Bank Mellat', 'بانک ملت'],
        '013' => ['Bank Refah Kargaran', 'بانک رفاه کارگران'],
        '014' => ['Bank Maskan', 'بانک مسکن'],
        '015' => ['Bank Sepah', 'بانک سپه'],
        '016' => ['Bank Keshavarzi', 'بانک کشاورزی'],
        '017' => ['Bank Melli Iran', 'بانک ملی ایران'],
        '018' => ['Bank Tejarat', 'بانک تجارت'],
        '019' => ['Bank Saderat Iran', 'بانک صادرات ایران'],
        '020' => ['Export Development Bank', 'بانک توسعه صادرات'],
        '021' => ['Post Bank', 'پست بانک ایران'],
        '022' => ["Tose'e Ta'avon Bank", 'بانک توسعه تعاون'],
        '051' => ["Tose'e Credit Institution", 'موسسه اعتباری توسعه'],
        '052' => ['Ghavamin Bank', 'بانک قوامین'],
        '053' => ['Bank Karafarin', 'بانک کارآفرین'],
        '054' => ['Bank Parsian', 'بانک پارسیان'],
        '055' => ['Bank Eghtesad Novin', 'بانک اقتصاد نوین'],
        '056' => ['Bank Saman', 'بانک سامان'],
        '057' => ['Bank Pasargad', 'بانک پاسارگاد'],
        '058' => ['Bank Sarmayeh', 'بانک سرمایه'],
        '059' => ['Bank Sina', 'بانک سینا'],
        '060' => ['Mehr Iran Credit Institution', 'موسسه قرض الحسنه مهر ایران'],
        '061' => ['Bank Shahr', 'بانک شهر'],
        '062' => ['Bank Ayandeh', 'بانک آینده'],
        '063' => ['Bank Ansar', 'بانک انصار'],
        '064' => ['Bank Gardeshgari', 'بانک گردشگری'],
        '065' => ['Bank Hekmat Iranian', 'بانک حکمت ایرانیان'],
        '066' => ['Bank Day', 'بانک دی'],
        '069' => ['Iran Zamin Bank', 'بانک ایران زمین'],
        '070' => ['Bank Resalat', 'بانک قرض‌الحسنه رسالت'],
        '073' => ['Kowsar Credit Institution', 'موسسه اعتباری کوثر'],
        '075' => ['Mellat Credit Institution', 'موسسه اعتباری ملل'],
        '078' => ['Khavarmianeh Bank', 'بانک خاورمیانه'],
        '079' => ['Mehr Eghtesad Bank', 'بانک مهر اقتصاد'],
        '090' => ['Mehr Iran Credit Institution', 'موسسه قرض الحسنه مهر ایران'],
        '080' => ['Noor Credit Institution', 'موسسه اعتباری نور'],
        '095' => ['Iran Venezuela Bi-National Bank', 'بانک ایران ونزوئلا'],
    ];

    public static function normalizeCard(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', en($raw)) ?? '';
        return strlen($digits) === 16 ? $digits : null;
    }

    public static function isValidCard(string $code): bool
    {
        if (!preg_match('/^\d{16}$/', $code) || preg_match('/^(\d)\1{15}$/', $code)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 16; $i++) {
            $d = (int) $code[$i];
            if ($i % 2 === 0) {
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
        }
        return $sum % 10 === 0;
    }

    public static function cardBank(string $code): ?array
    {
        return self::CARD_BINS[substr($code, 0, 6)] ?? null;
    }

    public static function cardDisplay(string $code): string
    {
        return "\u{2066}" . implode('-', str_split($code, 4)) . "\u{2069}";
    }

    public static function cardText(string $code): string
    {
        $ok   = self::isValidCard($code);
        $bank = self::cardBank($code);
        $bankLine = $bank !== null
            ? "\n" . L('🏦 بانک: ', '🏦 Bank: ') . L($bank[1], $bank[0])
            : '';
        return L("<b>💳 بررسی شماره کارت</b>\n\n", "<b>💳 Card number check</b>\n\n")
            . L('شماره: <code>', 'Number: <code>') . self::cardDisplay($code) . '</code>' . $bankLine . "\n"
            . ($ok ? L('✅ ساختار این شماره <b>درسته</b>.', '✅ The structure of this number is <b>valid</b>.') : L('❌ ساختار این شماره <b>اشتباهه</b>.', '❌ The structure of this number is <b>wrong</b>.')) . "\n\n"
            . L("<i>⚠️ فقط الگوریتم Luhn و بانک صادرکننده بررسی شده،\nنه صاحب واقعی کارت نزد بانک.</i>", "<i>⚠️ Only the Luhn algorithm and the issuing bank were checked,\nnot the card's actual owner at the bank.</i>");
    }

    public static function normalizeSheba(string $raw): ?string
    {
        $s = strtoupper(trim(en($raw)));
        $s = preg_replace('/[\s-]/', '', $s) ?? '';
        if (str_starts_with($s, 'IR')) {
            $s = substr($s, 2);
        }
        if (!preg_match('/^\d{24}$/', $s)) {
            return null;
        }
        return 'IR' . $s;
    }

    public static function isValidSheba(string $code): bool
    {
        if (!preg_match('/^IR\d{24}$/', $code)) {
            return false;
        }
        $rearranged = substr($code, 4) . 'IR' . substr($code, 2, 2);
        $numeric    = '';
        foreach (str_split($rearranged) as $ch) {
            $numeric .= ctype_digit($ch) ? $ch : (string) (ord($ch) - 55);
        }
        $rem = 0;
        foreach (str_split($numeric) as $d) {
            $rem = ($rem * 10 + (int) $d) % 97;
        }
        return $rem === 1;
    }

    public static function shebaBank(string $code): ?array
    {
        return self::SHEBA_CODES[substr($code, 4, 3)] ?? null;
    }

    public static function shebaDisplay(string $code): string
    {
        return "\u{2066}" . substr($code, 0, 4) . ' ' . implode(' ', str_split(substr($code, 4), 4)) . "\u{2069}";
    }

    public static function shebaText(string $code): string
    {
        $ok   = self::isValidSheba($code);
        $bank = self::shebaBank($code);
        $bankLine = $bank !== null
            ? "\n" . L('🏦 بانک: ', '🏦 Bank: ') . L($bank[1], $bank[0])
            : '';
        return L("<b>🏧 بررسی شماره شبا</b>\n\n", "<b>🏧 IBAN (Sheba) check</b>\n\n")
            . L('شبا: <code>', 'IBAN: <code>') . self::shebaDisplay($code) . '</code>' . $bankLine . "\n"
            . ($ok ? L('✅ رقم کنترل این شبا <b>درسته</b>.', '✅ The check digits of this IBAN are <b>valid</b>.') : L('❌ رقم کنترل این شبا <b>اشتباهه</b>.', '❌ The check digits of this IBAN are <b>wrong</b>.')) . "\n\n"
            . L("<i>⚠️ فقط رقم کنترل و بانک صادرکننده بررسی شده،\nنه صاحب واقعی حساب نزد بانک.</i>", "<i>⚠️ Only the check digits and the issuing bank were checked,\nnot the account's actual owner at the bank.</i>");
    }

    private const PHONE_OPERATORS = [
        'mci'      => ['MCI (Hamrah-e Aval)', 'همراه اول'],
        'irancell' => ['Irancell', 'ایرانسل'],
        'rightel'  => ['Rightel', 'رایتل'],
        'taliya'   => ['Taliya', 'تالیا'],
        'kish'     => ['Kish Cell', 'کیش‌سل'],
        'espadan'  => ['Espadan', 'اسپادان'],
        'shatel'   => ['Shatel Mobile', 'شاتل موبایل'],
        'aptel'    => ['Aptel', 'آپتل'],
        'mvno'     => ['Virtual operator (MVNO)', 'اپراتور مجازی'],
    ];

    private const PHONE_PREFIX_GROUPS = [
        'mci'      => ['0910', '0911', '0912', '0913', '0914', '0915', '0916', '0917', '0918', '0919', '0990', '0991'],
        'irancell' => ['0900', '0901', '0902', '0903', '0904', '0905', '0930', '0933', '0935', '0936', '0937', '0938', '0939', '0941'],
        'rightel'  => ['0920', '0921', '0922', '0923'],
        'taliya'   => ['0932'],
        'kish'     => ['0934'],
        'espadan'  => ['0931'],
        'shatel'   => ['09981'],
        'aptel'    => ['09991'],
        'mvno'     => ['0998', '0999'],
    ];

    private const MCI_REGIONS = [
        '0911' => ['Gilan, Mazandaran and Golestan', 'گیلان، مازندران و گلستان'],
        '0912' => ['Tehran', 'تهران'],
        '0913' => ['Isfahan, Kerman, Yazd and Chaharmahal and Bakhtiari', 'اصفهان، کرمان، یزد و چهارمحال و بختیاری'],
        '0914' => ['East Azerbaijan, West Azerbaijan and Ardabil', 'آذربایجان شرقی، آذربایجان غربی و اردبیل'],
        '0915' => ['Khorasan provinces and Sistan and Baluchestan', 'خراسان‌ها و سیستان و بلوچستان'],
        '0916' => ['Khuzestan and Lorestan', 'خوزستان و لرستان'],
        '0917' => ['Fars, Bushehr, Hormozgan and Kohgiluyeh and Boyer-Ahmad', 'فارس، بوشهر، هرمزگان و کهگیلویه و بویراحمد'],
        '0918' => ['Hamadan, Kermanshah, Kurdistan, Markazi and Ilam', 'همدان، کرمانشاه، کردستان، مرکزی و ایلام'],
    ];

    private const PHONE_AREAS = [
        '11' => ['Mazandaran', 'مازندران', 'Sari', 'ساری'],
        '13' => ['Gilan', 'گیلان', 'Rasht', 'رشت'],
        '17' => ['Golestan', 'گلستان', 'Gorgan', 'گرگان'],
        '21' => ['Tehran', 'تهران', 'Tehran', 'تهران'],
        '23' => ['Semnan', 'سمنان', 'Semnan', 'سمنان'],
        '24' => ['Zanjan', 'زنجان', 'Zanjan', 'زنجان'],
        '25' => ['Qom', 'قم', 'Qom', 'قم'],
        '26' => ['Alborz', 'البرز', 'Karaj', 'کرج'],
        '28' => ['Qazvin', 'قزوین', 'Qazvin', 'قزوین'],
        '31' => ['Isfahan', 'اصفهان', 'Isfahan', 'اصفهان'],
        '34' => ['Kerman', 'کرمان', 'Kerman', 'کرمان'],
        '35' => ['Yazd', 'یزد', 'Yazd', 'یزد'],
        '38' => ['Chaharmahal and Bakhtiari', 'چهارمحال و بختیاری', 'Shahr-e Kord', 'شهرکرد'],
        '41' => ['East Azerbaijan', 'آذربایجان شرقی', 'Tabriz', 'تبریز'],
        '44' => ['West Azerbaijan', 'آذربایجان غربی', 'Urmia', 'ارومیه'],
        '45' => ['Ardabil', 'اردبیل', 'Ardabil', 'اردبیل'],
        '51' => ['Razavi Khorasan', 'خراسان رضوی', 'Mashhad', 'مشهد'],
        '54' => ['Sistan and Baluchestan', 'سیستان و بلوچستان', 'Zahedan', 'زاهدان'],
        '56' => ['South Khorasan', 'خراسان جنوبی', 'Birjand', 'بیرجند'],
        '58' => ['North Khorasan', 'خراسان شمالی', 'Bojnord', 'بجنورد'],
        '61' => ['Khuzestan', 'خوزستان', 'Ahvaz', 'اهواز'],
        '66' => ['Lorestan', 'لرستان', 'Khorramabad', 'خرم‌آباد'],
        '71' => ['Fars', 'فارس', 'Shiraz', 'شیراز'],
        '74' => ['Kohgiluyeh and Boyer-Ahmad', 'کهگیلویه و بویراحمد', 'Yasuj', 'یاسوج'],
        '76' => ['Hormozgan', 'هرمزگان', 'Bandar Abbas', 'بندرعباس'],
        '77' => ['Bushehr', 'بوشهر', 'Bushehr', 'بوشهر'],
        '81' => ['Hamadan', 'همدان', 'Hamadan', 'همدان'],
        '83' => ['Kermanshah', 'کرمانشاه', 'Kermanshah', 'کرمانشاه'],
        '84' => ['Ilam', 'ایلام', 'Ilam', 'ایلام'],
        '86' => ['Markazi', 'مرکزی', 'Arak', 'اراک'],
        '87' => ['Kurdistan', 'کردستان', 'Sanandaj', 'سنندج'],
    ];

    public static function normalizePhone(string $raw): ?string
    {
        $d = preg_replace('/\D/', '', en($raw)) ?? '';
        if (str_starts_with($d, '0098')) {
            $d = substr($d, 4);
        } elseif (str_starts_with($d, '98') && strlen($d) === 12) {
            $d = substr($d, 2);
        } elseif (str_starts_with($d, '0')) {
            $d = substr($d, 1);
        }
        if (strlen($d) !== 10 || $d[0] === '0') {
            return null;
        }
        return '0' . $d;
    }

    public static function phoneIsMobile(string $code): bool
    {
        return isset($code[1]) && $code[1] === '9';
    }

    public static function phoneOperator(string $code): ?array
    {
        foreach ([substr($code, 0, 5), substr($code, 0, 4)] as $prefix) {
            foreach (self::PHONE_PREFIX_GROUPS as $key => $list) {
                if (in_array($prefix, $list, true)) {
                    return self::PHONE_OPERATORS[$key];
                }
            }
        }
        return null;
    }

    public static function phoneRegion(string $code): ?array
    {
        $prefix = substr($code, 0, 4);
        return self::MCI_REGIONS[$prefix] ?? null;
    }

    public static function phoneArea(string $code): ?array
    {
        return self::PHONE_AREAS[substr($code, 1, 2)] ?? null;
    }

    public static function isRecognizedPhone(string $code): bool
    {
        return self::phoneIsMobile($code) ? self::phoneOperator($code) !== null : self::phoneArea($code) !== null;
    }

    public static function phoneDisplay(string $code): string
    {
        if (self::phoneIsMobile($code)) {
            return "\u{2066}" . substr($code, 0, 4) . ' ' . substr($code, 4, 3) . ' ' . substr($code, 7) . "\u{2069}";
        }
        return "\u{2066}" . substr($code, 0, 3) . ' ' . substr($code, 3, 4) . ' ' . substr($code, 7) . "\u{2069}";
    }

    public static function phoneText(string $code): string
    {
        $ok     = self::isRecognizedPhone($code);
        $mobile = self::phoneIsMobile($code);
        $lines  = '';
        if ($mobile) {
            $op     = self::phoneOperator($code);
            $region = self::phoneRegion($code);
            $lines .= "\n" . L('📱 نوع: موبایل', '📱 Type: Mobile');
            $lines .= "\n" . L('📡 اپراتور: ', '📡 Operator: ') . ($op !== null ? L($op[1], $op[0]) : L('نامشخص', 'Unknown'));
            if ($region !== null) {
                $lines .= "\n" . L('📍 منطقه‌ی فروش: ', '📍 Sales region: ') . L($region[1], $region[0]);
            }
        } else {
            $area   = self::phoneArea($code);
            $lines .= "\n" . L('☎️ نوع: تلفن ثابت', '☎️ Type: Landline');
            $lines .= "\n" . L('📍 استان: ', '📍 Province: ') . ($area !== null ? L($area[1], $area[0]) : L('نامشخص', 'Unknown'));
            if ($area !== null) {
                $lines .= "\n" . L('🏙 مرکز: ', '🏙 Center: ') . L($area[3], $area[2]);
            }
            $lines .= "\n" . L('🔢 پیش‌شماره: ', '🔢 Area code: ') . "\u{2066}" . substr($code, 0, 3) . "\u{2069}";
        }
        $note = $mobile
            ? L("<i>⚠️ فقط از روی پیش‌شماره بررسی شده؛ شماره‌ی موبایل بین اپراتورها قابل انتقاله\nو استان دقیق صاحب خط مشخص نیست.</i>", "<i>⚠️ Checked by prefix only; mobile numbers can be ported between operators\nand the owner's exact province is unknown.</i>")
            : L("<i>⚠️ فقط از روی پیش‌شماره بررسی شده،\nنه صاحب واقعی خط.</i>", "<i>⚠️ Checked by area code only,\nnot the line's actual owner.</i>");
        return L("<b>📞 بررسی شماره تلفن</b>\n\n", "<b>📞 Phone number check</b>\n\n")
            . L('شماره: <code>', 'Number: <code>') . self::phoneDisplay($code) . '</code>' . $lines . "\n"
            . ($ok ? L('✅ پیش‌شماره‌ی این شماره <b>شناخته‌شده</b>ه.', '✅ The prefix of this number is <b>recognized</b>.') : L('❌ پیش‌شماره‌ی این شماره <b>ناشناخته</b>ست.', '❌ The prefix of this number is <b>unknown</b>.')) . "\n\n"
            . $note;
    }

    public static function qrUrl(string $text, int $size = 512): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
            'size' => "{$size}x{$size}", 'margin' => 12, 'format' => 'jpg', 'data' => $text,
        ]);
    }

    public static function dateText(string $place = ''): string
    {
        return Clock::text($place);
    }
}
