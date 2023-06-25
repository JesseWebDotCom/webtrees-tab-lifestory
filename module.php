<?php

declare(strict_types=1);

namespace JesseWebDotCom\Webtrees\Module\LifeStoryTab;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\FlashMessages;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleTabTrait;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleTabInterface;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Localization\Translation;

use Fisharebest\Webtrees\Age;
use Fisharebest\Webtrees\Date;

use function str_starts_with;   // will be added in PHP 8.0
use function explode;
use function implode;
use function count;
use function in_array;

/**
 * Class LifeStoryTabModule
 */
class LifeStoryTabModule extends AbstractModule implements ModuleTabInterface, ModuleCustomInterface, ModuleConfigInterface
{
    use ModuleTabTrait;
    use ModuleCustomTrait;
    use ModuleConfigTrait;

    /**
     * list of const for module administration
     */
    public const CUSTOM_TITLE       = 'Life Story';
    public const CUSTOM_MODULE      = 'webtrees-tab-lifestory';
    public const CUSTOM_DESCRIPTION = 'A tab showing an autogenerated life story of an individual.';
    public const CUSTOM_AUTHOR      = 'JesseWebDotCom';
    public const CUSTOM_WEBSITE     = 'https://github.com/JesseWebDotCom/' . self::CUSTOM_MODULE . '/';
    public const CUSTOM_VERSION     = '0.0.1';
    public const CUSTOM_LAST        = 'https://github.com/JesseWebDotCom/' .
                                      self::CUSTOM_MODULE. '/releases';


    /**
     * Build a life summary of an individual
     *
     * @param Individual $person
     * @return object
     */
    private function getAbout(Individual $person): string
    {
        /*
        BIRTH
        Example:
        John Joseph Pericas was born on April 9, 1931, in New York, New York, to Carmen Matias Lugo y Abolafia, age 29, 
        and Fernando Pericas y Vazquez, age 26.

        Phrases:
        - was born on
        - in
        - to
        - age
        - and

        Example:
        When John Fitzgerald Kennedy was born on 29 May 1917, in Brookline, Norfolk, Massachusetts, United States, 
        his father, Joseph Patrick Kennedy, Sr., was 28 and his mother, Rose Elizabeth Fitzgerald, was 26.

        Phrases:
        - When
        - was born on
        - in
        - his father
        - was
        - and
        - his mother
        */

        $sex = $person->sex();

        $about = '';

        // person
        $fullname = '<a href=' . $person->url() . '>' . $person->fullName() . '</a>';
        $firstname = $this->get_part(' ', strip_tags($person->fullName()), 0);

        // birth
        $birth_date  = $person->getBirthDate();
        $birth_estimated_date  = $person->getEstimatedBirthDate();
        $birth_place = $person->getBirthPlace();

        if ($birth_date->isOK()) {
            $about = $fullname . ' ' . $this->translate('was born', 'PAST') . ' ' . $this->translate('on') . ' ' .$birth_date->display();
            if ($birth_place->id() !== 0) {
                $about = $about . ' ' . $this->translate('in') . ' ' . $this->get_short_place($person->getBirthPlace()->gedcomName());
            }

            // parents
            $parents = $person->childFamilies()->first();
            if ($parents) {
                $father     = $parents->husband();
                if ($father) {
                    $father_fullname = '<a href=' . $father->url() . '>' . $father->fullName() . '</a>';
                    if ($father->getBirthDate()->isOK()) {
                        $fathers_age = (string) new Age($father->getBirthDate(), $person->getBirthDate());
                    }
                }

                $mother     = $parents->wife();
                if ($mother) {
                    $mother_fullname = '<a href=' . $mother->url() . '>' . $mother->fullName() . '</a>';
                    if ($mother->getBirthDate()->isOK()) {
                        $mothers_age = (string) new Age($mother->getBirthDate(), $person->getBirthDate());
                    }
                }

                if (strlen( $fathers_age ) !== 0 || strlen( $mothers_age ) !== 0) {
                    $about = ucwords($this->translate('when')) . ' ' . $about . ', ';
                }
                if (strlen( $fathers_age ) !== 0) {
                    $about = $about . $this->translate('ppronoun father', null, $sex) . ' ' . $father_fullname . ' ' . $this->translate('was') . ' ' . $this->get_part(' ', $fathers_age, 0); 
                }
                if (strlen( $fathers_age ) !== 0 && strlen( $mothers_age ) !== 0) {
                    $about = $about . ' and ' . $this->translate('ppronoun mother', null, $sex) . ' ' . $mother_fullname . ' ' . $this->translate('was') . ' ' . $this->get_part(' ', $mothers_age, 0); 
                } elseif (strlen( $mothers_age ) !== 0) {
                    $about = $about . ' ' . $this->translate('ppronoun mother', null, $sex) . ' ' . $mother_fullname . ' ' . $this->translate('was') . ' ' . $this->get_part(' ', $mothers_age, 0); 
                }                  
            }

            $about = $about . '. ';
        }
        

        return $about;
    }

    public function getAdminAction(): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        return $this->viewResponse($this->name() . '::settings', [
            'showparents' => $this->getPreference('showparents', '1'),
            'showsiblings' => $this->getPreference('showsiblings', '1'),
            'showmilitary' => $this->getPreference('showmilitary', '1'),
            'showjobs' => $this->getPreference('showjobs', '1'),
            'showresidences' => $this->getPreference('showresidences', '1'),
            'showfamilies' => $this->getPreference('showfamilies', '1'),
            'showtodayage' => $this->getPreference('showtodayage', '1'),            

            'title'        => $this->title()
        ]);
    }

    /**
     * Save the user preference.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getParsedBody();

        if ($params['save'] === '1') {

            // print_r($params);
            $this->setPreference('showparents', $params['showparents'] ?? '0');
            $this->setPreference('showsiblings', $params['showsiblings'] ?? '0');
            $this->setPreference('showmilitary', $params['showmilitary'] ?? '0');
            $this->setPreference('showjobs', $params['showjobs'] ?? '0');
            $this->setPreference('showresidences', $params['showresidences'] ?? '0');
            $this->setPreference('showfamilies', $params['showfamilies'] ?? '0');
            $this->setPreference('showtodayage', $params['showtodayage'] ?? '0');


            $message = I18N::translate('The preferences for the module “%s” have been updated.', $this->title());
            FlashMessages::addMessage($message, 'success');

        }

        return redirect($this->getConfigLink());
    }

    /**
     * save the user preferences for all parameters
     * that are not explicitly related to the extended family parts in the database
     *
     * @param array $params configuration parameters
     */
    private function postAdminActionOther(array $params)
    {
        $preferences = $this->listOfOtherPreferences();
        foreach ($preferences as $preference) {
            $this->setPreference($preference, $params[$preference]);
        }
    }

    /**
     * save the user preferences for all parameters related to this module in the database
     *
     * @param array $params configuration parameters
     */
    private function postAdminActionEfp(array $params)
    {
        $order = implode(",", $params['order']);
        $this->setPreference('order', $order);
        foreach (AboutSupport::listFamilyParts() as $efp) {
            $this->setPreference('status-' . $efp, '0');
        }
        foreach ($params as $key => $value) {
            if (str_starts_with($key, 'status-')) {
                $this->setPreference($key, $value);
            }
        }
    }

    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */
    public function title(): string
    {
        return /* I18N: Name of a module/tab on the individual page. */ I18N::translate(self::CUSTOM_TITLE);
    }

    /**
     * A sentence describing what this module does. Used in the list of all installed modules.
     *
     * @return string
     */
    public function description(): string
    {
        return /* I18N: Description of this module */ I18N::translate(self::CUSTOM_DESCRIPTION);
    }

    /**
     * The person or organisation who created this module.
     *
     * @return string
     */
    public function customModuleAuthorName(): string
    {
        return self::CUSTOM_AUTHOR;
    }

    /**
     * The version of this module.
     *
     * @return string
     */
    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    /**
     * A URL that will provide the latest version of this module.
     *
     * @return string
     */
    public function customModuleLatestVersionUrl(): string
    {
        return self::CUSTOM_LAST;
    }

    /**
     * Where to get support for this module?  Perhaps a GitHub repository?
     *
     * @return string
     */
    public function customModuleSupportUrl(): string
    {
        return self::CUSTOM_WEBSITE;
    }
    
    /**
     * Where does this module store its resources?
     *
     * @return string
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR;
    }

    /**
     * The default position for this tab can be changed in the control panel.
     *
     * @return int
     */
    public function defaultTabOrder(): int
    {
        return -1; // ensure tab is first
    }

    /**
     * Is this tab empty? If so, we don't always need to display it.
     *
     * @param Individual $individual
     * @return bool
     */
    public function hasTabContent(Individual $individual): bool
    {
        return true;
    }

    /**
     * A greyed out tab has no actual content, but perhaps have options to create content.
     *
     * @param Individual $individual
     * @return bool
     */
    public function isGrayedOut(Individual $individual): bool
    {
        return false;
    }

    /**
     * Where are the CCS specifications for this module stored?
     *
     * @return ResponseInterface
     *
     * @throws \JsonException
     */
    public function getCssAction() : ResponseInterface
    {
        return response(
            file_get_contents($this->resourcesFolder() . 'css' . DIRECTORY_SEPARATOR . self::CUSTOM_MODULE . '.css'),
            200,
            ['content-type' => 'text/css']
        );
    }

    /** {@inheritdoc} */
    public function getTabContent(Individual $individual): string
    {
        return view($this->name() . '::' . 'tab',
            [
                'about_content'            => $this->getAbout($individual),
            ]);
    }

    /** {@inheritdoc} */
    public function canLoadAjax(): bool
    {
        return false;
    }

    /**
     *  constructor
     */
    public function __construct()
    {
        // IMPORTANT - the constructor is called on *all* modules, even ones that are disabled.
        // It is also called before the webtrees framework is initialised, and so other components will not yet exist.
    }

    /**
     *  bootstrap
     */
    public function boot(): void
    {
        // Here is also a good place to register any views (templates) used by the module.
        // This command allows the module to use: view($this->name() . '::', 'fish')
        // to access the file ./resources/views/fish.phtml
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
    }
    
        /**
     * Additional/updated translations.
     *
     * @param string $language
     *
     * @return string[]
     */
    public function customTranslations(string $language): array
    {
        $lang_dir   = $this->resourcesFolder() . 'lang/';
        $extensions = array('mo', 'po');
        foreach ($extensions as &$extension) {
            $file       = $lang_dir . $language . '.' . $extension;
            if (file_exists($file)) {
                return (new Translation($file))->asArray();
            }
        }
        return [];
    }

    /* HELPERS ------------------------------------------------------------------------- */


    function translate($term, $context = null, $sex = null) {
        if ($context) {
            return I18N::translateContext($context, $this->replacePronoun($term, $sex));
        } else {
            return I18N::translate($this->replacePronoun($term, $sex));
        }
    }
    
    function replacePronoun($term, $sex) {
        if ($sex === 'M') {
            return str_replace('ppronoun', 'his', $term);
        } elseif ($sex === 'F') {
            return str_replace('ppronoun', 'her', $term);
        } else {
            return str_replace('ppronoun', 'their', $term);
        }
    }    

    /**
     * @return string
     */
    public function get_part(string $delim, string $phrase, int $number): string
    {
        $parts = explode($delim, $phrase);
        if (count($parts)===1) {
            return $phrase;
        } elseif ($number < 0) {
            return $parts[count($parts)+$number];
        } else {
            if (count($parts) >= $number-1) {
                return $parts[$number];
            } else {
                return '';
            }
        }
        return '';
    }

    /**
     * @return string
     */
    public function get_state_country(string $place): string
    {
        $death_place_state = $this->get_part(', ', $place, -2);
        $death_place_country = $this->get_part(', ', $place, -1);

        return join(', ', array_unique(array_filter(array($death_place_state, $death_place_country), 'strlen')));
    }    

    /**
     * @return string
     */
    public function get_short_place(string $place): string
    {
        $death_place_name = $this->get_part(', ', $place, 0);
        $death_place_state = $this->get_part(', ', $place, -2);
        $death_place_country = $this->get_part(', ', $place, -1);

        return join(', ', array_unique(array_filter(array($death_place_name, $death_place_state, $death_place_country), 'strlen')));
    }

    /**
     * @return string
     */
    public function join_words(array $words): string
    {
        $string = implode(', ', $words);
        if (count($words) == 1) {
            return $words[0];
        } elseif (count($words) > 1) {
            return substr_replace($string, ' and', strrpos($string, ','), 1);
        } else {
            return '';
        }
    }    

    /**
     * @return string
     */
    public function get_article(string $word): string
    {
        if (in_array(strtolower(substr($word, 0, 1)), array('a','e','i','o','u'))) {
            return 'an';
        } else {
            return 'a';
        }
    }    




  
}
return new LifeStoryTabModule;
