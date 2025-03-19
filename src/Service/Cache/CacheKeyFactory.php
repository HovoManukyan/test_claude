<?php

declare(strict_types=1);

namespace App\Service\Cache;

/**
 * Фабрика ключей кеша для централизованного управления ключами
 */
class CacheKeyFactory
{
    /**
     * Префиксы пространства кеша для разных типов сущностей
     */
    private const KEY_PREFIX_TEAM = 'team';
    private const KEY_PREFIX_PLAYER = 'player';
    private const KEY_PREFIX_BANNER = 'banner';
    private const KEY_PREFIX_TOURNAMENT = 'tournament';
    private const KEY_PREFIX_SKIN = 'skin';

    /**
     * Типы кешей
     */
    private const TYPE_ENTITY = 'entity';
    private const TYPE_LIST = 'list';
    private const TYPE_DETAIL = 'detail';
    private const TYPE_PAGE = 'page';

    /**
     * @param string $environment Текущее окружение приложения
     * @param bool $debug Включен ли режим отладки
     */
    public function __construct(
        private readonly string $environment,
        private readonly bool $debug
    ) {
    }

    /**
     * Создаёт ключ кеша для сущности команды
     *
     * @param int|string $identifier Идентификатор сущности
     * @return string Ключ кеша
     */
    public function teamEntity(int|string $identifier): string
    {
        return $this->buildKey(self::KEY_PREFIX_TEAM, self::TYPE_ENTITY, (string)$identifier);
    }

    /**
     * Создаёт ключ кеша для списка команд с фильтрами
     *
     * @param int $page Номер страницы
     * @param int $limit Элементов на странице
     * @param string|null $name Фильтр по имени
     * @param array|null $locales Фильтр по локали
     * @return string Ключ кеша
     */
    public function teamList(int $page, int $limit, ?string $name = null, ?array $locales = null): string
    {
        $params = [
            'page' => $page,
            'limit' => $limit,
            'name' => $name,
            'locales' => $locales,
        ];

        return $this->buildKey(self::KEY_PREFIX_TEAM, self::TYPE_LIST, $this->hashParams($params));
    }

    /**
     * Создаёт ключ кеша для детальной информации о команде
     *
     * @param string $slug Slug команды
     * @return string Ключ кеша
     */
    public function teamDetail(string $slug): string
    {
        return $this->buildKey(self::KEY_PREFIX_TEAM, self::TYPE_DETAIL, $slug);
    }

    /**
     * Создаёт ключ кеша для сущности игрока
     *
     * @param int|string $identifier Идентификатор игрока
     * @return string Ключ кеша
     */
    public function playerEntity(int|string $identifier): string
    {
        return $this->buildKey(self::KEY_PREFIX_PLAYER, self::TYPE_ENTITY, (string)$identifier);
    }

    /**
     * Создаёт ключ кеша для игрока по slug
     *
     * @param string $slug Slug игрока
     * @return string Ключ кеша
     */
    public function playerBySlug(string $slug): string
    {
        return $this->buildKey(self::KEY_PREFIX_PLAYER, 'slug', $slug);
    }

    /**
     * Создаёт ключ кеша для списка игроков с фильтрами
     *
     * @param int $page Номер страницы
     * @param int $limit Элементов на странице
     * @param bool|null $hasCrosshair Фильтр по наличию прицела
     * @param array|null $teamSlugs Фильтр по командам
     * @param string|null $name Фильтр по имени
     * @return string Ключ кеша
     */
    public function playerList(int $page, int $limit, ?bool $hasCrosshair = null, ?array $teamSlugs = [], ?string $name = null): string
    {
        $params = [
            'page' => $page,
            'limit' => $limit,
            'hasCrosshair' => $hasCrosshair,
            'teamSlugs' => $teamSlugs,
            'name' => $name,
        ];

        return $this->buildKey(self::KEY_PREFIX_PLAYER, self::TYPE_LIST, $this->hashParams($params));
    }

    /**
     * Создаёт ключ кеша для детальной информации об игроке
     *
     * @param string $slug Slug игрока
     * @return string Ключ кеша
     */
    public function playerDetail(string $slug): string
    {
        return $this->buildKey(self::KEY_PREFIX_PLAYER, self::TYPE_DETAIL, $slug);
    }

    /**
     * Создаёт ключ кеша для сущности баннера
     *
     * @param int|string $identifier Идентификатор баннера
     * @return string Ключ кеша
     */
    public function bannerEntity(int|string $identifier): string
    {
        return $this->buildKey(self::KEY_PREFIX_BANNER, self::TYPE_ENTITY, (string)$identifier);
    }

    /**
     * Создаёт ключ кеша для баннера по странице
     *
     * @param string $pageIdentifier Идентификатор страницы
     * @return string Ключ кеша
     */
    public function bannerByPage(string $pageIdentifier): string
    {
        return $this->buildKey(self::KEY_PREFIX_BANNER, self::TYPE_PAGE, $pageIdentifier);
    }

    /**
     * Создаёт ключ кеша для сущности турнира
     *
     * @param int|string $identifier Идентификатор турнира
     * @return string Ключ кеша
     */
    public function tournamentEntity(int|string $identifier): string
    {
        return $this->buildKey(self::KEY_PREFIX_TOURNAMENT, self::TYPE_ENTITY, (string)$identifier);
    }

    /**
     * Создаёт ключ кеша для списка турниров с фильтрами
     *
     * @param int $page Номер страницы
     * @param int $limit Элементов на странице
     * @param string|null $region Фильтр по региону
     * @param string|null $tier Фильтр по уровню
     * @return string Ключ кеша
     */
    public function tournamentList(int $page, int $limit, ?string $region = null, ?string $tier = null): string
    {
        $params = [
            'page' => $page,
            'limit' => $limit,
            'region' => $region,
            'tier' => $tier,
        ];

        return $this->buildKey(self::KEY_PREFIX_TOURNAMENT, self::TYPE_LIST, $this->hashParams($params));
    }

    /**
     * Создаёт ключ кеша для сущности скина
     *
     * @param int|string $identifier Идентификатор скина
     * @return string Ключ кеша
     */
    public function skinEntity(int|string $identifier): string
    {
        return $this->buildKey(self::KEY_PREFIX_SKIN, self::TYPE_ENTITY, (string)$identifier);
    }

    /**
     * Создаёт ключ кеша для списка скинов с фильтрами
     *
     * @param int $page Номер страницы
     * @param int $limit Элементов на странице
     * @param string|null $name Фильтр по имени
     * @return string Ключ кеша
     */
    public function skinList(int $page, int $limit, ?string $name = null): string
    {
        $params = [
            'page' => $page,
            'limit' => $limit,
            'name' => $name,
        ];

        return $this->buildKey(self::KEY_PREFIX_SKIN, self::TYPE_LIST, $this->hashParams($params));
    }

    /**
     * Строит ключ кеша из частей
     *
     * @param string $prefix Префикс типа сущности
     * @param string $type Тип операции (entity, list, detail)
     * @param string $identifier Идентификатор
     * @return string Ключ кеша
     */
    private function buildKey(string $prefix, string $type, string $identifier): string
    {
        // Включаем окружение в ключ кеша, чтобы избежать конфликтов между окружениями
        return sprintf('%s_%s_%s_%s', $this->environment, $prefix, $type, $identifier);
    }

    /**
     * Хэширует параметры для создания предсказуемого идентификатора
     *
     * @param array $params Параметры
     * @return string Хэшированная строка
     */
    private function hashParams(array $params): string
    {
        // Сортируем параметры для обеспечения постоянного порядка
        ksort($params);

        // Обрабатываем параметры массива
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                sort($value);
                $params[$key] = implode('_', $value);
            }

            // Преобразуем null/bool значения в строки
            if ($value === null) {
                $params[$key] = 'null';
            } elseif (is_bool($value)) {
                $params[$key] = $value ? 'true' : 'false';
            }
        }

        // Создаем строковое представление параметров
        $paramsString = http_build_query($params);

        // Для читаемости в dev сохраняем полный ключ, в prod используем хэш
        if ($this->debug) {
            return $paramsString;
        }

        // Используем md5 для фиксированной длины ключей, безопасных для бэкендов кеша
        return md5($paramsString);
    }
}