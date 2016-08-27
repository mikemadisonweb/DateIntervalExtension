<?php

namespace Madison\Twig;

use Exception;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernel;

class DateIntervalExtension extends \Twig_Extension
{
    /**
     * @var string
     */
    private $locale;

    /*
     * Listen to the 'kernel.request' event to get the main request, this has several reasons:
     *  - The request can not be injected directly into a Twig extension, this causes a ScopeWideningInjectionException
     *  - Retrieving the request inside of the 'localize_route' method might yield us an internal request
     *  - Requesting the request from the container in the constructor breaks the CLI environment (e.g. cache warming)
     */
    public function onKernelRequest(GetResponseEvent $event) {
        if ($event->getRequestType() === HttpKernel::MASTER_REQUEST) {
            $this->locale = $event->getRequest()->getLocale();
        }
    }

    /**
     * Plural forms for different languages
     * @var array
     */
    private $forms = [
        'en' => [
            'years' => ['year', 'years'],
            'months' => ['month', 'months'],
            'days' => ['day', 'days'],
            'hours' => ['hour', 'hours'],
            'minutes' => ['minute', 'minutes'],
            'seconds' => ['second', 'seconds']
        ],
        'ru' => [
            'years' => ['год', 'года', 'лет'],
            'months' => ['месяц', 'месяца', 'месяцев'],
            'days' => ['день', 'дня', 'дней'],
            'hours' => ['час', 'часа', 'часов'],
            'minutes' => ['минута', 'минуты', 'минут'],
            'seconds' => ['секунда', 'секунды', 'секунд']
        ]
    ];

    /**
     * List of supported languages
     * @var array
     */
    private $supported = ['eng' => true, 'rus' => true];

    /**
     * @@return array
     */
    public function getFilters()
    {
        return array(
            'interval' => new \Twig_SimpleFilter('interval', array($this, 'getInterval')),
            'age' => new \Twig_SimpleFilter('age', array($this, 'getAge')),
        );
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'date_interval_extension';
    }

    /**
     * Get date interval as a string
     * @param   \DateTime|string        $dateFrom
     * @param   \DateTime|string|null   $dateTill
     * @param   int                     $max        Max number of time components (ex. 3 would be: x years y months z days)
     * @param   string                  $separator
     * @return  string
     * @internal param \DateTime|string $date
     */
    public function getInterval($dateFrom, $dateTill = null, $max = 3, $separator = ' ')
    {
        $this->convertStringToDate($dateFrom);
        if(is_null($dateTill)) {
            // Set till now then
            $dateTill = new \DateTime();
        } else {
            $this->convertStringToDate($dateTill);
        }

        return $this->getIntervalString($dateFrom, $dateTill, $max, $separator);
    }

    /**
     * Get string representation of date
     * @param   \DateTime               $dateFrom
     * @param   \DateTime               $dateTill
     * @param   int                     $max
     * @param   string                  $separator
     * @return  string
     * @throws  Exception
     */
    private function getIntervalString(\DateTime $dateFrom, \DateTime $dateTill, $max, $separator)
    {
        $timeComponents = (array) $dateFrom->diff($dateTill);
        $notNullTimeComponents = array_values(array_filter(array_slice($timeComponents, 0, 6)));

        if(($max > 6) && ($max < 1) && !is_numeric($max)) {
            throw new \Exception('Max number of units should be a number between 1 and 6.');
        }
        $interval = [];
        for($i=0; $i<$max; $i++){
            // If diff returns 0 years we will start from months if it's not empty either
            if(isset($notNullTimeComponents[$i])) {
                $formForNotNullComponent = array_values(array_slice($this->forms[$this->locale], -count($notNullTimeComponents)));
                $interval[] = $this->pluralRules($notNullTimeComponents[$i], $formForNotNullComponent[$i]);
            }
        }

        return implode($separator, $interval);
    }

    /**
     * Формы склонения слов в зависимости от числительного
     *
     * @param int $number Число, для которого слово нужно просклонять
     * @param $wordForms
     * @return string
     * @throws \Exception
     */
    private function pluralRules($number, $wordForms)
    {
        switch ($this->locale){
            case 'en':
                return $number." ".(($number === 1) ? $wordForms[0] : $wordForms[1]);
            case 'ru':
                $cases = array (2, 0, 1, 1, 1, 2);
                return $number." ".$wordForms[ ($number%100 > 4 && $number %100 < 20) ? 2 : $cases[min($number%10, 5)] ];
            default:
                $supported = join(', ', array_keys($this->supported));
                throw new \Exception("Wrong language argument passed. Supported languages are ({$supported})");
        }
    }

    /**
     * Convert string to DateTime object
     * @param string $date
     * @return \DateTime
     * @throws Exception
     */
    private function convertStringToDate($date)
    {
        if (!$date instanceof \DateTime) {
            if (!is_string($date)) {
                throw new \Exception('Expected formatted string of \DateTime object.');
            }
            $timestamp = $this->getTimestamp($date);
            $date = new \DateTime();
            $date->setTimestamp($timestamp);
        }

        return $date;
    }

    /**
     * Convert string to timestamp
     * @param string $date
     * @return integer
     * @throws \Exception
     */
    private function getTimestamp($date)
    {
        if(!$this->isValidTimeStamp(strtotime($date))) {
            throw new \Exception('Not valid date string.');
        }

        return strtotime($date);
    }

    /**
     * Validate timestamp
     * @param $timestamp
     * @return bool
     */
    private function isValidTimeStamp($timestamp)
    {
        if(ctype_digit($timestamp) && strtotime(date('Y-m-d H:i:s',$timestamp)) === (int)$timestamp) {
            return true;
        } else {
            return false;
        }
    }
}