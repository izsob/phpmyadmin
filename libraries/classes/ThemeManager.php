<?php

declare(strict_types=1);

namespace PhpMyAdmin;

use function __;
use function array_key_exists;
use function closedir;
use function htmlspecialchars;
use function is_dir;
use function ksort;
use function opendir;
use function readdir;
use function sprintf;
use function trigger_error;

use const DIRECTORY_SEPARATOR;
use const E_USER_ERROR;
use const E_USER_WARNING;

/**
 * phpMyAdmin theme manager
 */
class ThemeManager
{
    /**
     * ThemeManager instance
     */
    private static ThemeManager|null $instance = null;

    /** @var string file-system path to the theme folder */
    private string $themesPath;

    /** @var string path to theme folder as an URL */
    private string $themesPathUrl;

    /** @var array<string,Theme> available themes */
    public array $themes = [];

    /** @var string  cookie name */
    public string $cookieName = 'pma_theme';

    public bool $perServer = false;

    /** @var string name of active theme */
    public string $activeTheme = '';

    /** @var Theme Theme active theme */
    public Theme $theme;

    public string $themeDefault;

    /** @const string The name of the fallback theme */
    public const FALLBACK_THEME = 'pmahomme';

    public function __construct()
    {
        $this->themes = [];
        $this->themeDefault = self::FALLBACK_THEME;
        $this->activeTheme = '';
        $this->themesPath = self::getThemesFsDir();
        $this->themesPathUrl = self::getThemesDir();

        $this->setThemePerServer($GLOBALS['cfg']['ThemePerServer']);

        $this->loadThemes();

        $this->theme = new Theme();

        $configThemeExists = true;

        if (! $this->checkTheme($GLOBALS['cfg']['ThemeDefault'])) {
            trigger_error(
                sprintf(
                    __('Default theme %s not found!'),
                    htmlspecialchars($GLOBALS['cfg']['ThemeDefault']),
                ),
                E_USER_ERROR,
            );
            $configThemeExists = false;
        } else {
            $this->themeDefault = $GLOBALS['cfg']['ThemeDefault'];
        }

        // check if user have a theme cookie
        $cookieTheme = $this->getThemeCookie();
        if ($cookieTheme && $this->setActiveTheme($cookieTheme)) {
            return;
        }

        if ($configThemeExists) {
            // otherwise use default theme
            $this->setActiveTheme($this->themeDefault);
        } else {
            // or fallback theme
            $this->setActiveTheme(self::FALLBACK_THEME);
        }
    }

    /**
     * Returns the singleton ThemeManager object
     *
     * @return ThemeManager The instance
     */
    public static function getInstance(): ThemeManager
    {
        return self::$instance ??= new ThemeManager();
    }

    /**
     * sets if there are different themes per server
     *
     * @param bool $perServer Whether to enable per server flag
     */
    public function setThemePerServer($perServer): void
    {
        $this->perServer = (bool) $perServer;
    }

    /**
     * Sets active theme
     *
     * @param string|null $theme theme name
     */
    public function setActiveTheme(string|null $theme): bool
    {
        if (! $this->checkTheme($theme)) {
            trigger_error(
                sprintf(
                    __('Theme %s not found!'),
                    htmlspecialchars((string) $theme),
                ),
                E_USER_ERROR,
            );

            return false;
        }

        $this->activeTheme = $theme;
        $this->theme = $this->themes[$theme];

        // need to set later
        //$this->setThemeCookie();

        return true;
    }

    /**
     * Returns name for storing theme
     *
     * @return string cookie name
     */
    public function getThemeCookieName(): string
    {
        // Allow different theme per server
        if (isset($GLOBALS['server']) && $this->perServer) {
            return $this->cookieName . '-' . $GLOBALS['server'];
        }

        return $this->cookieName;
    }

    /**
     * returns name of theme stored in the cookie
     *
     * @return string|false theme name from cookie or false
     */
    public function getThemeCookie(): string|false
    {
        $GLOBALS['config'] ??= null;

        $name = $this->getThemeCookieName();
        if ($GLOBALS['config']->issetCookie($name)) {
            return $GLOBALS['config']->getCookie($name);
        }

        return false;
    }

    /**
     * save theme in cookie
     *
     * @return true
     */
    public function setThemeCookie(): bool
    {
        $GLOBALS['config']->setCookie(
            $this->getThemeCookieName(),
            $this->theme->id,
            $this->themeDefault,
        );
        // force a change of a dummy session variable to avoid problems
        // with the caching of phpmyadmin.css.php
        $GLOBALS['config']->set('theme-update', $this->theme->id);

        return true;
    }

    public function loadThemes(): void
    {
        $this->themes = [];
        $dirHandle = opendir($this->themesPath);

        if ($dirHandle === false) {
            trigger_error('Error: cannot open themes folder: ./themes', E_USER_WARNING);

            return;
        }

        while (($dir = readdir($dirHandle)) !== false) {
            if ($dir === '.' || $dir === '..' || ! @is_dir($this->themesPath . $dir)) {
                continue;
            }

            if (array_key_exists($dir, $this->themes)) {
                continue;
            }

            $newTheme = Theme::load($this->themesPathUrl . $dir, $this->themesPath . $dir . DIRECTORY_SEPARATOR, $dir);
            if (! $newTheme instanceof Theme) {
                continue;
            }

            $this->themes[$dir] = $newTheme;
        }

        closedir($dirHandle);
        ksort($this->themes);
    }

    /**
     * checks if given theme name is a known theme
     *
     * @param string|null $theme name fo theme to check for
     */
    public function checkTheme(string|null $theme): bool
    {
        return array_key_exists($theme ?? '', $this->themes);
    }

    public function getThemesArray(): array
    {
        $themes = [];
        foreach ($this->themes as $theme) {
            $themes[] = [
                'id' => $theme->getId(),
                'name' => $theme->getName(),
                'version' => $theme->getVersion(),
                'is_active' => $theme->getId() === $this->activeTheme,
            ];
        }

        return $themes;
    }

    public static function initializeTheme(): Theme
    {
        $themeManager = self::getInstance();

        return $themeManager->theme;
    }

    /**
     * Return the themes directory with a trailing slash
     */
    public static function getThemesFsDir(): string
    {
        return ROOT_PATH . 'public/themes' . DIRECTORY_SEPARATOR;
    }

    /**
     * Return the themes directory with a trailing slash as a relative public path
     */
    public static function getThemesDir(): string
    {
        return './themes/';// This is an URL
    }
}
