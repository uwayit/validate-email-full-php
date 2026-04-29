<?php

declare(strict_types=1);

/**
 * Бібліотека якісної валідації email на стороні сервера.
 * Повністю синхронізована з JS версією та оптимізована для PHP 8.4+.
 * 
 * @author uwayit
 * @link https://github.com/uwayit/validate-email-full-jquery
 */
class validateEmail
{
    private string $email;
    private array $errors = [];
    private array $epart = [];

    public function __construct(string $email)
    {
        $this->email = $email;
        $this->process();
    }

    /**
     * Основний конвеєр обробки та валідації
     */
    private function process(): void
    {
        // 1. Попередня підготовка (очищення від пробілів, нижній регістр)
        $this->initialPreparation();

        // 2. Перевірка на баги плейсхолдерів
        if (empty($this->email) || $this->email === 'email' || $this->email === 'youremail') {
            $this->addError('placeholder');
            return;
        }

        // 3. Очищення від "www."
        $this->cleanWww();

        // 4. Очищення від "+" (фільтри)
        $this->clearPlus();

        // 5. Виправлення типових помилок у доменах (напр. gmail.com.ua -> gmail.com)
        $this->fixCommonMisspellings();

        // 6. Розбір на частини (epart) та автоматичне виправлення зон/імен
        if (!$this->makeEpart()) {
            $this->addError('regulyarTest'); // Якщо не вдалося розібрати - це помилка синтаксису
            return;
        }

        // 7. Перевірка на цифри у домені
        if ($this->numberTest($this->epart['domainAll'])) {
            $this->addError('numberTestTest');
            return;
        }

        // 8. Перевірка на "брехливі" пошти
        if ($this->isLie($this->email)) {
            $this->addError('isLieTest');
            return;
        }

        // 9. Сувора перевірка регулярним виразом
        if (!$this->validateRegex($this->email)) {
            $this->addError('regulyarTest');
            return;
        }

        // 10. Перевірка на анонімайзери
        if ($this->stopAnonimayzer($this->epart['domainAll'])) {
            $this->addError('stopAnonimayzerTest');
            return;
        }

        // 11. Перевірка синтаксису (подвійні крапки, початок з символу тощо)
        if ($this->sintaksisValid($this->epart)) {
            $this->addError('sintaksisValidTest');
            return;
        }

        // 12. Довжина доменної зони
        if ($this->domainZoneLength($this->epart['domainZone'])) {
            $this->addError('domainZoneLenght');
            return;
        }

        // 13. Перевірка за чорним списком доменів
        if ($this->stopDomainALL($this->epart)) {
            $this->addError('stopDomainALLTest');
            return;
        }

        // 14. Однолітерні домени
        if ($this->oneLetter($this->epart['domainAll'])) {
            $this->addError('oneLetterTest');
            return;
        }

        // 15. Заборонені зони
        if ($this->badZone($this->epart['domainZone'])) {
            $this->addError('badZoneTest');
            return;
        }

        // 16. Нормалізація (приведення синонімів до стандарту)
        $this->email = $this->buildStandartEmail($this->email);
        $this->makeEpart(); // Перезбираємо після нормалізації

        // 17. Yandex телефони
        if ($this->yaPhone($this->epart)) {
            $this->addError('yaphoneTest');
            return;
        }

        // 18. Мінімальна довжина локальної частини
        if ($this->minLength($this->epart)) {
            $this->addError('minLengthTest');
            return;
        }

        // 19. Заборонені підкреслення (tireStop)
        if ($this->tireStop($this->epart['domainOnly'], $this->epart['localPart'])) {
            $this->addError('tireStopTest');
            return;
        }

        // 20. Користувацький бан-лист (neNa)
        if ($this->neNa($this->epart['domainAll'])) {
            $this->addError('neNaTest');
            return;
        }
    }

    /**
     * Очищення email від пробілів та лідируючого плюса
     */
    private function initialPreparation(): void
    {
        // Видаляємо всі пробіли
        $this->email = preg_replace('/\s+/', '', trim($this->email));
        $this->email = strtolower($this->email);

        if (str_starts_with($this->email, '+')) {
            $this->email = substr($this->email, 1);
        }
    }

    /**
     * Видалення "www." на початку
     */
    private function cleanWww(): void
    {
        if (str_starts_with($this->email, 'www.')) {
            $this->email = substr($this->email, 4);
        }
    }

    /**
     * Видалення додаткових фільтрів після плюса (user+filter@gmail.com -> user@gmail.com)
     */
    private function clearPlus(): void
    {
        $posPlus = strrpos($this->email, '+');
        $posAt = strpos($this->email, '@');

        if ($posPlus !== false && $posAt !== false && $posPlus < $posAt) {
            $this->email = substr($this->email, 0, $posPlus) . substr($this->email, $posAt);
        }
    }

    /**
     * Виправлення найбільш типових друкарських помилок
     */
    private function fixCommonMisspellings(): void
    {
        $replacements = [
            'yandex.com.ua' => 'yandex.ua',
            'gmail.com.ua' => 'gmail.com',
        ];
        $this->email = str_replace(array_keys($replacements), array_values($replacements), $this->email);
    }

    /**
     * Розбір email на складові та автоматичне виправлення помилок у назвах/зонах
     */
    private function makeEpart(): bool
    {
        $lastAt = strrpos($this->email, '@');
        if ($lastAt === false) return false;

        $localPart = substr($this->email, 0, $lastAt);
        $domainAll = substr($this->email, $lastAt + 1);

        $lastPoint = strrpos($domainAll, '.');
        if ($lastPoint === false) return false;

        $domainOnly = substr($domainAll, 0, $lastPoint);
        $domainZone = substr($domainAll, $lastPoint + 1);

        // Автоматичне виправлення зони
        $domainZone = $this->correctDomainZone($domainZone);
        
        // Автоматичне виправлення імені домену
        $domainOnly = $this->correctName($domainOnly);

        $this->epart = [
            'localPart' => $localPart,
            'domainAll' => $domainOnly . '.' . $domainZone,
            'domainOnly' => $domainOnly,
            'domainZone' => $domainZone,
        ];

        $this->sborka();
        return true;
    }

    /**
     * Збирання email з частин
     */
    private function sborka(): void
    {
        $this->email = $this->epart['localPart'] . '@' . $this->epart['domainOnly'] . '.' . $this->epart['domainZone'];
    }

    /**
     * Виправлення помилкових доменних зон через match (PHP 8.0+)
     */
    private function correctDomainZone(string $zone): string
    {
        return match (true) {
            in_array($zone, ['ry', 'rv', 'ri', 'rn', 'tu', 'ty', 'my']) => 'ru',
            in_array($zone, ['cjm', 'cpm', 'kom', 'gom', 'vom', 'con', 'kon', 'cm', 'om', 'cim', 'som', 'xom', 'cox']) => 'com',
            in_array($zone, ['orq', 'opq', 'opg']) => 'org',
            $zone === 'ya' => 'ua',
            in_array($zone, ['ner', 'het', 'bet', 'nen', 'nit', 'met', 'ney', 'ne', 'nwt']) => 'net',
            default => $zone
        };
    }

    /**
     * Виправлення помилкових імен доменів
     */
    private function correctName(string $name): string
    {
        return match (true) {
            in_array($name, ['yandax', 'yandeks', 'yandx', 'yangex', 'jandex', 'yadex', 'uandex', 'yndex', 'ayndex']) => 'yandex',
            in_array($name, ['digmir', 'biqmir', 'diqmir']) => 'bigmir',
            in_array($name, ['mfil', 'meil', 'msil', 'maij', 'maill', 'mil', 'imeil', 'mael', 'maii', 'mali', 'mal', 'majl', 'maul', 'masl', 'maik', 'ail', 'naul', 'nail']) => 'mail',
            in_array($name, ['cloud', 'ikloud', 'iclout', 'icloub', 'cloub']) => 'icloud',
            in_array($name, [
                'gamailcom', 'gmaill', 'gmailco', 'gmel', 'qm', 'gmjl', 'gmm', 'gmaa', 'ggmai', 'cmal', 'cail', 'gail',
                'gmal', 'gmei', 'gmaij', 'gmajl', 'qnail', 'gnail', 'gmeil', 'gmall', 'jmail', 'gmaii', 'gmali', 'hmail',
                'gmael', 'jimal', 'jmeil', 'qhail', 'gmoil', 'ghail', 'cmail', 'gamil', 'dmail', 'gmaik', 'gmоil', 'gimajl',
                'gimail', 'qemail', 'gomail', 'gemeil', 'gemail', 'gamail', 'gameil', 'gmaul', 'qeimal', 'glail', 'gmaile',
                'goi', 'qoi', 'gmfql', 'gmd'
            ]) => 'gmail',
            default => $name
        };
    }

    /**
     * Перевірка на наявність цифр у домені
     */
    private function numberTest(string $domainAll): bool
    {
        return preg_match('/\d/', $domainAll) === 1;
    }

    /**
     * Перевірка на "фейкові" адреси та збіг з хостом
     */
    private function isLie(string $email): bool
    {
        $atPos = strrpos($email, '@');
        $domain = $atPos !== false ? substr($email, $atPos + 1) : '';
        $host = $_SERVER['HTTP_HOST'] ?? '';

        if ($domain === $host || 'www.' . $domain === $host) {
            return true;
        }

        $badEmails = [
            'mail@mail.ru', 'gmail@gmail.com', 'email@mail.ua', 'email@example.com', 'test@test.com'
        ];

        return in_array($email, $badEmails);
    }

    /**
     * Валідація регулярним виразом
     */
    private function validateRegex(string $email): bool
    {
        return (bool)preg_match('/^([a-zA-Z0-9._%+-]+)@([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,12}$/', $email);
    }

    /**
     * Перевірка на анонімайзери
     */
    private function stopAnonimayzer(string $domainAll): bool
    {
        $anonimayzers = [
            'scryptmail.com', '10minutemail.com', '10minutemail.net', 'guerrillamail.com', 'mailinator.com',
            'temp-mail.org', 'dropmail.me', 'dispostable.com', 'trashmail.com', 'yopmail.com'
        ];
        return in_array($domainAll, $anonimayzers);
    }

    /**
     * Перевірка синтаксису (подвійні символи тощо)
     */
    private function sintaksisValid(array $epart): bool
    {
        $restrictedDomains = ['ya.ru', 'yandex', 'mail.ru', 'bk.ru', 'mail.ua', 'inbox.ru', 'gmail.com', 'list.ru'];
        $invalidPatterns = ['..', '-.', '.-', '_.', '._', '--', '-_', '_-', '__'];

        $isRestricted = false;
        foreach ($restrictedDomains as $rd) {
            if (str_contains($epart['domainAll'], $rd)) {
                $isRestricted = true;
                break;
            }
        }

        if ($isRestricted) {
            foreach ($invalidPatterns as $pattern) {
                if (str_contains($epart['localPart'], $pattern)) return true;
            }
        }

        // Початок або кінець з символу
        $first = $epart['localPart'][0] ?? '';
        $last = $epart['localPart'][strlen($epart['localPart']) - 1] ?? '';
        $symbols = ['.', '-', '_'];

        if (in_array($first, $symbols) || in_array($last, $symbols)) return true;

        // Глибина піддоменів
        if (count(explode('.', $epart['domainAll'])) > 3) return true;

        // Цифри в зоні
        if (preg_match('/\d/', $epart['domainZone'])) return true;

        return false;
    }

    /**
     * Перевірка довжини зони
     */
    private function domainZoneLength(string $zone): bool
    {
        return strlen($zone) > 15;
    }

    /**
     * Чорний список доменів та зон
     */
    private function stopDomainALL(array $epart): bool
    {
        $badDomains = [
            'com.ua', 'ua.com', 'kom.ua', 'kis.ru', 'kom.ru', 'com.ru', 'ru.com', 'meil.com', 'mael.com', 'emeil.ru', 'emeil.com', 'imeil.ua', 'com.com',
            'net.ua', 'net.ru', 'com.net', 'example.com', 'sitemail.com', 'site.com', 'email.com', 'mailcom.ru',
            'yahoo.net', 'hotmail.ru', 'ramler.ru', 'ramdler.ru', 'rambler.com', 'yaho.com',
            'ua.net', 'ykr.net', 'ykt.net', 'ukt.net', 'ucr.net', 'ukr.com',
            'bigmir.ua', 'bigmir.com',
            'gmail.ru', 'gmail.ua', 'gmail.com.ua', 'gmail.com.ru',
            'ya.ua', 'ya.com', 'yande.ru', 'yande.ua',
            'inboks.ru', 'indox.ru', 'list.ua', 'list.com', 'iist.ru', 'iist.ua',
            'bk.com', 'bk.ua', 'dk.com', 'br.com', 'dk.ru', 'br.ru', 'bl.ru', 'bj.ru',
            'vk.ru', 'vk.com', 'vkontakte.ru', 'mail.com', 'mail.com.ua', 'mail.com.ru',
        ];
        $badZones = ['yy', 'aa'];

        return in_array($epart['domainAll'], $badDomains) || in_array($epart['domainZone'], $badZones);
    }

    /**
     * Заборона однолітерних доменів
     */
    private function oneLetter(string $domainAll): bool
    {
        if (str_contains($domainAll, 'i.ua') || str_contains($domainAll, 'a.ua')) return false;
        
        $parts = explode('.', $domainAll);
        return strlen($parts[0] ?? '') === 1;
    }

    /**
     * Сміттєві зони
     */
    private function badZone(string $zone): bool
    {
        return in_array($zone, ['xxx', 'biz', 'cc']);
    }

    /**
     * Нормалізація email до еталонного вигляду
     */
    private function buildStandartEmail(string $email): string
    {
        if (preg_match('/@(?:yandex\.[a-z]{2,3}|ya\.ru|narod\.ru)$/i', $email)) {
            [$box] = explode('@', $email);
            return str_replace('-', '.', $box) . '@yandex.ru';
        }

        if (preg_match('/@(gmail\.com|googlemail\.com)$/i', $email)) {
            [$box] = explode('@', $email);
            return str_replace('.', '', $box) . '@gmail.com';
        }

        if (preg_match('/@(pm\.me|proton\.me|protonmail\.com)$/i', $email)) {
            [$box] = explode('@', $email);
            return str_replace('.', '', $box) . '@proton.me';
        }

        if (preg_match('/@(icloud\.com)$/i', $email)) {
            [$box] = explode('@', $email);
            return str_replace('.', '', $box) . '@icloud.com';
        }

        if (preg_match('/@(ymail\.com)$/i', $email)) {
            [$box] = explode('@', $email);
            return $box . '@yahoo.com';
        }

        return $email;
    }

    /**
     * Перевірка Yandex телефонів
     */
    private function yaPhone(array $epart): bool
    {
        if (preg_match('/yandex|ya\.ru/', $epart['domainAll'])) {
            $phoneCodes = ['/^380/', '/^37/', '/^99/', '/^79/', '/^89/', '/^77/'];
            foreach ($phoneCodes as $code) {
                if (preg_match($code, $epart['localPart']) && preg_match('/^\d{11,13}$/', $epart['localPart'])) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Мінімальна довжина локальної частини
     */
    private function minLength(array $epart): bool
    {
        $domainRules = [
            'i.ua' => 6, 'ro.ru' => 6, 'r0.ru' => 6, 'rambler.ru' => 6, 'lenta.ru' => 6,
            'myrambler.ru' => 6, 'gmail.com' => 5, 'mail.ru' => 3, 'mail.ua' => 3,
            'inbox.ru' => 3, 'list.ru' => 3, 'bk.ru' => 3
        ];
        $min = $domainRules[$epart['domainAll']] ?? 3;
        return strlen($epart['localPart']) < $min;
    }

    /**
     * Заборона підкреслень у певних сервісах
     */
    private function tireStop(string $domainOnly, string $localPart): bool
    {
        return str_contains($domainOnly, 'yandex') && str_contains($localPart, '_');
    }

    /**
     * Користувацький бан-лист
     */
    private function neNa(string $domainAll): bool
    {
        return in_array($domainAll, ['my.com', 'rambler.ua']);
    }

    /**
     * Додавання помилки до списку
     */
    private function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    /**
     * Чи є помилки
     */
    public function hasError(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Отримання результату
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Отримання першої помилки або всього списку
     */
    public function getError(bool $all = false): string|array
    {
        return $all ? $this->errors : ($this->errors[0] ?? '');
    }
}
