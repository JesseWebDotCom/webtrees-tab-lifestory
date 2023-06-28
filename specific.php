<?php
    //This file contains all custom code specific to the webtrees-tab-lifestory module
    
    use Fisharebest\Webtrees\I18N;
    use Fisharebest\Webtrees\Individual;
    use Fisharebest\Webtrees\Age;
    use Fisharebest\Webtrees\Date;

    // Get a custom person object with the cleaned data we care about
    function getPerson(Individual $individual, object $module): object
    {
        $person = (object)[];
        $person->self = $individual;

        // person
        $person->link = '<a href=' . $individual->url() . '>' . $individual->fullName() . '</a>';
        $person->firstname = get_part(' ', strip_tags($individual->fullName()), 0);

         // birth        
         $person->birth_date_type = '';
         if ($individual->getBirthDate()->isOK()) {
            $person->birth_date = $individual->getBirthDate()->display();
            $person->birth_date_type = 'exact';
        } elseif ($individual->getEstimatedBirthDate()->isOK()) {
            $person->birth_date = str_replace('estimated', 'around', $individual->getEstimatedBirthDate()->display());
            $person->birth_date_type = 'estimated';
        }

        if ($individual->getBirthPlace()->id() !== 0) {        
            $person->birth_place = '<a href=' . $individual->getBirthPlace()->url() . '>' . $individual->getBirthPlace()->shortName() . '</a>';
        }

        // parents
        if ((bool) $module->getPreference('showparents')) { 
            $parents = $individual->childFamilies()->first();        
            if ($parents) {
                $father = $parents->husband();
                if ($father) {
                    $person->father_link = '<a href=' . $father->url() . '>' . $father->fullName() . '</a>';
                    if ($father->getBirthDate()->isOK()) {
                        $person->birth_father_age = extractNumericAge((string) new Age($father->getBirthDate(), $individual->getBirthDate()));
                    }
                }  
                $mother = $parents->wife();
                if ($mother) {
                    $person->mother_link = '<a href=' . $mother->url() . '>' . $mother->fullName() . '</a>';
                    if ($mother->getBirthDate()->isOK()) {
                        $person->birth_mother_age = extractNumericAge((string) new Age($mother->getBirthDate(), $individual->getBirthDate()));
                    }
                }   
            }
            
            // // adoption
            // $adoptive_parents = array();
            // foreach ($person->self->facts() as $fact) {
            //     if ($fact->tag()=='INDI:ADOP') {
            //         array_push($adoptive_parents, $fact->value());
            //     }
            // }
            // if (count($adoptive_parents) > 0) {
            //     $adoptive_parents_phrase = str_replace('and', translate('and'), join_words($adoptive_parents));
            //     $lifestory = $lifestory . ucwords(translate('pronoun', null, $sex)) . ' ' . translate('was adopted by', 'PAST') . ' ' . $adoptive_parents_phrase . '. ';
            // }
        }

        // education
        $person->schools = array();
        if ((bool) $module->getPreference('showeducation')) {                  
            foreach ($individual->facts() as $fact) {
                if ($fact->tag()=='INDI:EDUC' || $fact->tag()=='EDUCATION') {
                    $school = $fact->value();
                    if (strlen($school)===0) {
                        $school = $this->get_part(', ', $fact->attribute('PLAC'), 0);
                    }
                    array_push($person->schools, $school);
                }
            }
        }

        // death   
        if ($person->self->isDead()) {     
            $person->death_date_type = '';
            $deathDate = $individual->getDeathDate();
            $estimatedDeathDate = $individual->getEstimatedDeathDate();
            
            if ($deathDate->isOK()) {
                $person->death_date = $deathDate->display();
                $person->death_date_type = 'exact';
            } elseif ($estimatedDeathDate->isOK()) {
                $person->death_date = str_replace('estimated', 'around', $estimatedDeathDate->display());
                $person->death_date_type = 'estimated';
            }
            
            $person->death_age = extractNumericAge((string) new Age($individual->getBirthDate(), $deathDate->isOK() ? $deathDate : $estimatedDeathDate));
                      
            foreach ($individual->facts() as $fact) {
                if ($fact->tag()=='INDI:DEAT') {
                    if (!empty($fact->attribute('CAUS'))) {
                        $person->death_cause = $fact->attribute('CAUS');
                    }                    
                }
            }           
            
            if ($individual->getDeathPlace()->id() !== 0) {        
                $person->death_place = '<a href=' . $individual->getDeathPlace()->url() . '>' . $individual->getDeathPlace()->shortName() . '</a>';
            }  
            
            
        }

        return $person;
    }

    // Build a life summary of an individual
    function getLifeStory(Individual $individual, object $module): string
    {
        $person = getPerson($individual, $module); 
        $story = "";

        // BIRTH
        $msgctxt = ($person->birth_date_type === 'exact') ? "EXACT_DATE" : (($person->birth_date_type === 'estimated') ? "ESTIMATED_DATE" : "NO_DATE");
        $story = $story . translate('WAS_BORN', 'EXACT_DATE', [$person->link, $person->birth_date]);

        if (isset($person->birth_place)) $story = $story . ' ' . translate('IN_LOCATION', null, [$person->birth_place]);

        if (isset($person->father_link)) {
            $story = $story . ' ' . translate('TO_PARENT', null, [$person->father_link]);
            if (isset($person->birth_father_age)) $story = $story . ' (' . translate('AGE', null, [$person->birth_father_age]) . ')';
        } 

        if (isset($person->father_link) && isset($person->mother_link)) $story = $story . ' ' . translate('AND');

        if (isset($person->mother_link)) {
            $story = $story . ' ' . translate('TO_PARENT', null, [$person->mother_link]);
            if (isset($person->birth_mother_age)) $story = $story . ' (' . translate('AGE', null, [$person->birth_mother_age]) . ')';
        } 
        $story = $story . '.';
        $story = $story . '<br><br>';

        // EDUCATION
        if(!empty($person->schools)) {
            $schools = join_words($person->schools, translate('AND'));
            $story = $story . ' ' . translate('ATTENDED_SCHOOLS', null, [$person->firstname, $schools]) . '.';
            $story = $story . '<br><br>';
        }

        // DEATH
        if ($person->self->isDead()) {

            $msgctxt = ($person->death_date_type === 'exact') ? "EXACT_DATE" : (($person->death_date_type === 'estimated') ? "ESTIMATED_DATE" : "NO_DATE");
            $story = $story . translate('DIED', 'EXACT_DATE', [$person->link, $person->death_date]);

            if (isset($person->death_cause)) $story = $story . ' (' . translate('DEATH_CAUSE', null, [$person->death_cause]) . ')';
            if (isset($person->death_place)) $story = $story . ' ' . translate('IN_LOCATION', null, [$person->death_place]);
            if (isset($person->death_age)) $story = $story . ' ' . translate('AT_AGE', null, [$person->death_age]);

            $story = $story . '.';
            $story = $story . '<br><br>';
        }

        $story = $story . '<br><br>';
        return $story;
    }    

    // HELPERS -----------------------------------
    function extractNumericAge($ageString): string
    {
        return preg_replace('/[^0-9]/', '', $ageString);
    }

    function translate($term, $context = null, $replacements = []) {
        // I18N::translateContext('EXACT_DATE', 'WAS_BORN', $person->link, $person->birth_date);
        // I18N::translate('IN_LOCATION', $person->birth_place);
    
        // Translate the term
        if ($context) {
            if (!empty($replacements)) {
                $translatedTerm = I18N::translateContext($context, $term, ...$replacements);
            } else {
                $translatedTerm = I18N::translateContext($context, $term);
            }
        } else {
            if (!empty($replacements)) {
                $translatedTerm = I18N::translate($term, ...$replacements);
            } else {
                $translatedTerm = I18N::translate($term);
            }
        }
    
        return $translatedTerm;
    }       
    
    /**
     * @return string
     */
    function get_part(string $delim, string $phrase, int $number): string
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
    function get_state_country(string $place): string
    {
        $death_place_state = get_part(', ', $place, -2);
        $death_place_country = get_part(', ', $place, -1);
    
        return join(', ', array_unique(array_filter(array($death_place_state, $death_place_country), 'strlen')));
    }    
    
    /**
     * @return string
     */
    function get_short_place(string $place): string
    {
        $death_place_name = get_part(', ', $place, 0);
        $death_place_state = get_part(', ', $place, -2);
        $death_place_country = get_part(', ', $place, -1);
    
        return join(', ', array_unique(array_filter(array($death_place_name, $death_place_state, $death_place_country), 'strlen')));
    }
    
    /**
     * @return string
     */
    function join_words(array $words, string $conjunction): string
    {
        $string = implode(', ', $words);
        if (count($words) == 1) {
            return $words[0];
        } elseif (count($words) > 1) {
            return substr_replace($string, ' ' . $conjunction, strrpos($string, ','), 1);
        } else {
            return '';
        }
    }    
    
    /**
     * @return string
     */
    function get_article(string $word): string
    {
        if (in_array(strtolower(substr($word, 0, 1)), array('a','e','i','o','u'))) {
            return 'an';
        } else {
            return 'a';
        }
    }      
?>