<?php
//
// Бібліотека якісної валідації email на стороні сервера

class validateEmail
    {

    private $email = '';
    private $error = [];

    private $epart = [];
    // Послідовно проходимося по функціях і опрацьовуємо отриманий email
    // PHP бібліотека на стадії дошліфовки
    function __construct($email)
        {
        // Тихо видаляє з поля з email УСІ пробіли та знак "+" на початку мила
        // та приводимо до нижнього регістру
        $this->initialPreparation($email);

        // Виявляємо можливі баги інпат полів та плейсхолдерів
        $this->checkBugPlaceholder();

        // Тихо видаляємо www в мильнику, бо то явно помилка і йдемо далі
        $this->cleanWww();
        // Деперсоналізуємо email видаляючи з нього додаткові фільтруючі патерни
        // myemail+work@gmail.com = myemail@gmail.com
        $this->clearPlus();


        // Розбираємо email на частини
        $this->makeEpart();
        // Перевіряємо на наявність цифр в домені
        $this->numberTest();

        // Перевіряємо синтаксис імені
        $this->sintaksisValid();

        // Стандартизуємо email
        $this->buildStandartEmail();
        // Якщо довжина email до собачки надто коротка
        $this->minLength();
        // Деякі поштовики забороняють використовувати _ в адресі
        $this->tireStop();

        }




    // Тихо видаляє з поля з email УСІ пробіли та знак "+" на початку мила
// та приводимо до нижнього регістру
    public function initialPreparation($email)
        {
        if ($email) {
            // Видаляємо пробіли на початку і в кінці рядка та всі пробіли всередині рядка
            $email = trim($email);
            $email = str_replace(' ', '', $email);
            $email = strtolower($email);

            // Видаляємо плюс на початку
            if ($email[0] === '+') {
                $email = substr($email, 1);
                }
            $this->email = $email;
            }
            if($this->email == '' or $this->email == 'email'){
            $this->error[] = 'empty';
            $this->email = '';
            }

        }



    public function checkBugPlaceholder()
        {
        // Далі нічого не робимо, якщо раніше помічена хоча б одна помилка
        if (!empty($this->error))
            return false;

        if (empty($this->email) or $this->email == 'email' or $this->email == 'youremail') {
            $this->error[] = 'placeholder';
            $this->email = '';
            }
        }

    // Тихо видаляємо www в мильнику, бо то явно помилка і йдемо далі
    // АЛЕ
    // Краще насправді не видаляти і не йти далі, а просити клієнта все перевірити і виправити помилки самостійно
    // Бо кліент вірогідно дуже погано розуміє що таке email і де його взяти
    // Отож статистично, якщо він вводить email починаючи з www то там неправильно не тільки це, а взагалі все введено від балди
    // Тож я просто видаляю www, а ви можете наприклад видати помилку (приклади виводу помилок вище)
    public function cleanWww()
        {
        // Далі нічого не робимо, якщо раніше помічена хоча б одна помилка
        if (!empty($this->error))
            return false;

        if (strpos($this->email, "www.") === 0) {
            substr($this->email, 4);
            }
        }


    // якщо плюс у середині мила: Тихо видаляє знак плюс "+" і все, що після нього до собачки
    // Дозволяє деперсоналізувати введення email
    public function clearPlus()
        {
        // Далі нічого не робимо, якщо раніше помічена хоча б одна помилка
        if (!empty($this->error))
            return false;

        $pos_plus = strrpos($this->email, '+');
        if ($pos_plus > 0) {
            $this->email = substr($this->email, 0, $pos_plus) . substr($this->email, strpos($this->email, '@'));
            }
        }



    // Розбираємо email на частини
    public function makeEpart()
        {
        // Якщо точка є, то тут буде кількість символів до точки
        $this->epart['lastPoint'] = strrpos($this->email, '.');
        // Якщо собачка є, то тут буде кількість символів до собачки
        $this->epart['lastAt'] = strrpos($this->email, '@');
        // Одержуємо доменну зону (наприклад, 'com')
        $this->epart['domainZone'] = $this->correctDomainZone(substr($this->email, $this->epart['lastPoint'] + 1));
        // Одержуємо все до собачки (локальну частину)
        $this->epart['localPart'] = substr($this->email, 0, $this->epart['lastAt']);
        // Одержуємо весь домен (наприклад, 'gmail.com')
        $this->epart['domainAll'] = substr($this->email, $this->epart['lastAt'] + 1);
        // Одержуємо лише домен без зони (наприклад, 'gmail')
        $this->epart['domainOnly'] = substr($this->email, $this->epart['lastAt'] + 1, $this->epart['lastPoint'] - $this->epart['lastAt'] - 1);
        // Про всяк випадок, щоб застосувати зміни що зроблено вище
        $this->sborka();    
    }

    // Про всяк випадок, щоб застосувати зміни
        public function sborka(){
        $this->email = $this->epart['localPart'] . '@' . $this->epart['domainOnly'] . '.' . $this->epart['domainZone'];
        }




    // Перевіряємо на наявність цифр в домені
    public function numberTest()
        {
        // Далі нічого не робимо, якщо раніше помічена хоча б одна помилка
        if (!empty($this->error))
            return false;

        if (preg_match('/\d/', $this->epart['domainAll']) === 1) {

            return true;
            }
        return false;
        }


    // Ми відштовхуємося від логіки, що:
    // 1. email це унікальний ідентифікатор користувача
    // 2. під одним email можна зареєструвати лише один аккаунт
    // В зв'язку з цим ми закриваємо цією функцією всі можливості множинних використань одного email
    public function buildStandartEmail()
        {
        // Далі нічого не робимо, якщо раніше помічена хоча б одна помилка
        if (!empty($this->error))
            return false;
        // Видаляємо пробіли на початку і в кінці рядка та всі пробіли всередині рядка
        $email = trim($this->email);
        $email = preg_replace('/\s+/', '', $email);
        $email = strtolower($email);

        // За замовчуванням так і запишемо якщо нічого не змінимо
        $StandartEmail = $email;

        // Конвертуємо всі можливі варіації Яндекс ящиків в еталонний формат mail.mail@yandex.ru
        // Не найкраще рішення використовувати .[a-z]{2,3} але зато коротко
        if (preg_match('/@(?:yandex\.[a-z]{2,3}|ya\.ru|narod\.ru)$/i', $email)) {
            list($box, $domain) = explode('@', $email, 2);
            $StandartEmail = str_replace('-', '.', $box) . '@yandex.ru';
            }

        // Конвертуємо всі можливі варіації ГУГЛ ящиків на еталонний формат mailmail@gmail.com
        if (preg_match('/@(gmail\.com|googlemail\.com)$/i', $email)) {
            list($box, $domain) = explode('@', $email, 2);
            $StandartEmail = str_replace('.', '', $box) . '@gmail.com';
            }

        // Конвертуємо всі можливі варіації ProtonMail ящиків на еталонний формат box@proton.me
        if (preg_match('/@(pm\.me|proton\.me|protonmail\.com)$/i', $email)) {
            list($box, $domain) = explode('@', $email, 2);
            $StandartEmail = str_replace('.', '', $box) . '@proton.me';
            }
        // ВИдаляємо точки в icloud
        if (preg_match('/@(icloud\.com)$/i', $email)) {
            list($box, $domain) = explode('@', $email, 2);
            $StandartEmail = str_replace('.', '', $box) . '@icloud.com';
            }
        // Конвертуємо yahoo
        if (preg_match('/@(ymail\.com)$/i', $email)) {
            list($box, $domain) = explode('@', $email, 2);
            $StandartEmail = str_replace('.', '', $box) . '@yahoo.com';
            }
        $this->email = $StandartEmail;
        }






    // Не даємо клієнту вказувати відверто брехливі email
    public function isLie()
        {
        // Якщо клієнт на сайті example.com подає заявку
        // то всі скриньки з домену @example.com не можна використовувати як скриньку
        $doman_email = explode('@', $this->email);
        $host = core::getCurrentDomainByServerHttpHost();
        if ($doman_email[1] == $host) {
            return true;
            }
        $email_not_you = [
            // Відверті невірні та/або брехливі ящики
            // Достовірно відомо - таких немає у клієнтів
            // Сюди можна також внести наприклад наші email або якісь конкретні
            'mail@mail.ru',
            'gmail@gmail.com',
            'email@mail.ua',
            'email@example.com'
        ];
        foreach ($email_not_you as $invalidEmail) {
            if ($invalidEmail == $this->email) {
                // Разом з відхиленням email ми можемо
                // запам'ятовувати факт спроби вказівки клієнтом брехливої ​​інформації
                // та передати цю інформацію на сервер разом із заявкою
                // Я не розробляв, але десь це може знадобитись
                return true;
                }
            }
        // Якщо з email все ок
        return false;
        }

    // Додаткова валідація регулярним виразом
    // Грубо первіряє на коректність конструкції
    // Не дозволяє кирилицю
    static function validateEmail($email)
        {
        $re = '/^([a-zA-Z0-9._%+-]+)@([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,12}$/';
        return preg_match($re, $email) === 1;
        }

    // cannot contain an underdash "_" in the address
    public function tireStop()
        {
        // Далі нічого не робимо, якщо раніше помічена хоча б одна помилка
        if (!empty($this->error))
            return false;
        if (
            (strpos($this->epart['domainOnly'], 'yandex') !== false) &&
            (strpos($this->epart['localPart'], '_') !== false)
        ) {
            $this->error[] = 'tireStop';
            }
        }

    // Якщо Ваші листи не можливо доставити на якісь домени, або ви НЕ хочете доставляти на них
// можна недопускати вказання юзерами цих email
    static function neNa($domainAll)
        {
        if ($domainAll == 'my.com') {
            return 'my.com';
            }
        if ($domainAll == 'rambler.ua') {
            return 'rambler.ua';
            }
        return false;
        }


    // email які починаються з номерів телефонів в яндексі є синонімами основних ящиків
    // Тож в ідеалі треба забороняти використовувати yandex email що починаються з телефонів
    // І вимагати вказання кореневого email
    public static function yaPhone($epart)
        {
        // Перевіряємо, чи містить домен "yandex" або "ya.ru"
        if (preg_match('/yandex|ya\.ru/', $epart['domainAll'])) {
            $phoneCodes = [
                '/^380/', // Україна
                '/^37/',  // Білорусь, Молдова, Латвія, Вірменія
                '/^99/',  // Грузія, Киргизстан, Таджикистан, Узбекистан
                '/^79/',  // РФ
                '/^89/',  // РФ
                '/^77/'   // Казахстан
            ];

            // Якщо локальна частина починається з телефонного коду і містить тільки цифри
            foreach ($phoneCodes as $code) {
                if (preg_match($code, $epart['localPart']) && preg_match('/^\d{11,13}$/', $epart['localPart'])) {
                    return true;
                    }
                }
            }
        return false;
        }


    // Виявляємо чи використовує юзер анонімайзер, тобто тимчасову, однохвилину пошту
    public static function stopAnonimayzer($domainAll)
        {
        $anonimayzer = [
            // УВАГА!!! Всі домени та зони нижче потрібно вводити в нижньому регістрі
            // Перелічимо які хочемо анонімайзери через кому
            'scryptmail.com'
        ];

        foreach ($anonimayzer as $domain) {
            if ($domain === $domainAll) {
                return true; // Помилка
                }
            }
        return false;
        }

    // Виправляємо помилкову зону на коректну
    public static function correctDomainZone($domainZone)
        {
        $corrections = [
            // Всі ці помилки засновані на реальному досвіді
            'ru' => ['ry', 'rv', 'ri', 'rn', 'tu', 'ty', 'my'],
            'com' => ['cjm', 'cpm', 'kom', 'gom', 'vom', 'con', 'kon', 'cm', 'om', 'cim', 'som', 'xom', 'cox'],
            'org' => ['orq', 'opq', 'opg'],
            'ua' => ['ya'],
            'net' => ['ner', 'het', 'bet', 'nen', 'nit', 'met', 'ney', 'ne', 'nwt']
        ];

        foreach ($corrections as $correctZone => $errors) {
            if (in_array($domainZone, $errors)) {
                return $correctZone;
                }
            }

        return $domainZone; // Повертає початкову зону, якщо вона не знайдена серед помилкових
        }

    // Достовірно помилкові домени що виправляються автоматично на коректні
    // Найпоширеніші друкарські помилки
    // ! ЧЕРЕЗ СПЕЦИФІКИ ПЕРЕВІРКИ ДОМЕНУ (за входженням на початку), сюди не варто включати варіант на кшталт
    // ! 'gmai','gma', -- бо буде робити даремну заміну шила на шило
    public function correctName()
        {
        // Перевірка та мовчазне непомітне для клієнта виправлення найбільш типових та явних друкарських помилок у мильниках
        $this->email = str_replace('gmail.com.ua', 'gmail.com', $this->email);

        $corrections = [
            'yandex' => ['yandax', 'yandeks', 'yandx', 'yangex', 'jandex', 'yadex', 'uandex', 'yndex', 'ayndex'],
            'bigmir' => ['digmir', 'biqmir', 'diqmir'],
            'mail' => [
                'mfil',
                'meil',
                'msil',
                'maij',
                'maill',
                'mil',
                'imeil',
                'mael',
                'maii',
                'mali',
                'mal',
                'majl',
                'maul',
                'masl',
                'maik',
                'ail',
                'naul',
                'nail'
            ],
            'icloud' => ['icloud', 'cloud', 'ikloud', 'iclout', 'icloub', 'cloub'],
            // GMAIL.COM та ім'я йому ЛЕГІОН
            'gmail' => [
                'gamailcom',
                'gmaill',
                'gmailco',
                'gmel',
                'qm',
                'gmjl',
                'gmm',
                'gmaa',
                'ggmai',
                'cmal',
                'cail',
                'gail',
                'gmal',
                'gmei',
                'gmaij',
                'gmajl',
                'qnail',
                'gnail',
                'gmeil',
                'gmall',
                'jmail',
                'gmaii',
                'gmali',
                'hmail',
                'gmael',
                'jimal',
                'jmeil',
                'qhail',
                'gmoil',
                'ghail',
                'cmail',
                'gamil',
                'dmail',
                'gmaik',
                'gmоil',
                'gimajl',
                'gimail',
                'qemail',
                'gomail',
                'gemeil',
                'gemail',
                'gamail',
                'gameil',
                'gmaul',
                'qeimal',
                'glail',
                'gmaile',
                'goi',
                'qoi',
                'gmfql',
                'gmd'
            ]
        ];

        foreach ($corrections as $correctName => $errors) {
            if (in_array($this->epart['domainOnly'], $errors)) {
                $this->epart['domainOnly'] = $correctName;
                $this->email = $this->epart['localPart'] . '@' . $this->epart['domainOnly'];
                }
            }
        }

    // Не даємо клієнту вказувати email на перелічених нижче доменах та зонах
    // Здебільшого це неймовірно тупі помилки
    // Дєякі помилки можна було б виправляти автоматично, але ми ітак багато помилок виправляємо автоматично і це вже нюанси
    public static function stopDomainALL($epart)
        {
        $email_not_valid = [
            // УВАГА!!! Всі домени та зони нижче потрібно вводити в нижньому регістрі
            // УВАГА!!! Всі домени та зони нижче потрібно вводити в нижньому регістрі
            'com.ua',
            'ua.com',
            'kom.ua',
            'kis.ru',
            'kom.ru',
            'com.ru',
            'ru.com',
            'meil.com',
            'mael.com',
            'emeil.ru',
            'emeil.com',
            'imeil.ua',
            'com.com',
            'net.ua',
            'net.ru',
            'com.net',
            'example.com',
            'sitemail.com',
            'site.com',
            'email.com',
            'mailcom.ru',

            // Разные мелкачи типа yahoo rambler hotmail яблоки icloud и т.п.  и связанные с ними опечатки
            'yahoo.net',
            'hotmail.ru',
            'ramler.ru',
            'ramdler.ru',
            'rambler.com',
            'yaho.com',
            // Популярные украинские почтовики
            'ua.net',
            'ykr.net',
            'ykt.net',
            'ukt.net',
            'ucr.net',
            'ukr.com',

            'bigmir.ua',
            'bigmir.com',

            // Осторожней с этим GMAIL
            'gmail.ru',
            'gmail.ua',
            'gmail.com.ua',
            'gmail.com.ru',

            // YANDEX 
            'ya.ua',
            'ya.com',
            'yande.ru',
            'yande.ua',

            // MAIL.RU и вся их орда
            'inboks.ru',
            'indox.ru',
            'list.ua',
            'list.com',
            'iist.ru',
            'iist.ua',
            'bk.com',
            'bk.ua',
            'dk.com',
            'br.com',
            'dk.ru',
            'br.ru',
            'bl.ru',
            'bj.ru',
            'vk.ru',
            'vk.com',
            'vkontakte.ru',
            'mail.com',
            'mail.com.ua',
            'mail.com.ru',
        ];



        $domain_not_valid = [
            // Зони які точно не вірні і які неможливо розібрати
            'yy',
            'aa'
        ];

        foreach ($email_not_valid as $invalidEmail) {
            if ($invalidEmail === $epart['domainAll']) {
                return true; // Помилка
                }
            }

        foreach ($domain_not_valid as $invalidDomain) {
            if ($epart['domainZone'] === $invalidDomain) {
                return true; // Помилка
                }
            }

        return false;
        }

    // доменное имя не может быть из одной буквы, за исключением I.UA и A.UA, 
    // другие же любые международные сервисы которые мыслимыми способами могут использоваться(типа x.com) это ошибка
    public static function oneLetter($domainAll)
        {
        if (isset($domainAll)) {
            if (strpos($domainAll, 'i.ua') === false && strpos($domainAll, 'a.ua') === false) {
                if (strlen($domainAll) === 1) {
                    return true;
                    }
                }
            }

        return false;
        }
    // Якщо домена зона складається більше ніж з 5 букв, то це помилка
// Спірне рішення, бо корпоративні пошти можуть були усілякі - .place наприклад
// 3 - якшо сайт НЕ розрахований під корпоративних клієнтів
// На фронті має бути теж відрегульовано
    function domainZoneLength($domainZone)
        {
        return strlen($domainZone) > 5; 
        }
    // Якщо домена зона - є в цьому переліку, то повертаємо помилку
    function badZone($domainZone)
        {
        $badZones = ['xxx', 'biz', 'cc'];
        return in_array($domainZone, $badZones);
        }

    // Мінімальна довжина для деяких поштових скриньок
    public function minLength()
        {
        // Далі нічого не робимо, якщо раніше помічена хоча б одна помилка
        if (!empty($this->error))
            return false;
        $domainRules = [
            'i.ua' => 6,
            'ro.ru' => 6,
            'r0.ru' => 6,
            'rambler.ru' => 6,
            'lenta.ru' => 6,
            'myrambler.ru' => 6,
            'gmail.com' => 5,
            'mail.ru' => 3,
            'mail.ua' => 3,
            'inbox.ru' => 3,
            'list.ru' => 3,
            'bk.ru' => 3
        ];

        $minLength = isset($domainRules[$this->epart['domainAll']]) ? $domainRules[$this->epart['domainAll']] : 3;
        $test = strlen($this->epart['localPart']) < $minLength;
        if($test){
            $this->error[] = 'minLength';
        }
        }

    // Інші перевірки всі на купу
    public function sintaksisValid()
        {
        // Далі нічого не робимо, якщо раніше помічена хоча б одна помилка
        if (!empty($this->error))
            return false;
        // Перелік поштовиків які ТОЧНО не допускають два "символи", що йдуть один за одним
        $restrictedDomains = ['ya.ru', 'yandex', 'mail.ru', 'bk.ru', 'mail.ua', 'inbox.ru', 'gmail.com', 'list.ru'];
        $invalidPatterns = ['..', '-.', '.-', '_.', '._', '--', '-_', '_-', '__'];

        foreach ($restrictedDomains as $domain) {
            if (strpos($this->epart['domainAll'], $domain) !== false) {
                foreach ($invalidPatterns as $pattern) {
                    if (strpos($this->epart['localPart'], $pattern) !== false) {
                        $this->error[] = 'sintaksisValid';
                        }
                    }
                }
            }

        if (isset($this->epart['localPart'][0])) {
            // Мило не може починатися з наступних символів у жодної з поштовиків
            if (
                strpos($this->epart['localPart'][0], '.') !== false ||
                strpos($this->epart['localPart'][0], '-') !== false ||
                strpos($this->epart['localPart'][0], '_') !== false
            ) {
                $this->error[] = 'sintaksisValid';
                }

            // Мило  безпосередньо перед собачкою не може містити наступні символи в жодної з поштовиків
            $lastChar = $this->epart['localPart'][strlen($this->epart['localPart']) - 1];
            if (
                strpos($lastChar, '.') !== false ||
                strpos($lastChar, '-') !== false ||
                strpos($lastChar, '_') !== false
            ) {
                $this->error[] = 'sintaksisValid';
                }
            }

        // Якщо домен 4-го та вище рівнів - повертаємо помилку
        $email_array = explode('.', $this->epart['domainAll']);
        if (count($email_array) > 3) {
            $this->error[] = 'sintaksisValid';
            }

        // Якщо в доменій зоні є цифри
        $ext = end($email_array);
        if (preg_match('/\d/', $ext)) {
            $this->error[] = 'sintaksisValid';
            }

        }

    // Відаємо опрацьований email
    public function getEmail()
        {
        return $this->email;
        }
    // Повідомляємо, чи були якісь помилки підчас опрацювання email
    public function getError()
        {
        return $this->error;
        }




    } // class 

