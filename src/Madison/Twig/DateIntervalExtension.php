<?php

namespace Madison\Twig;

use Exception;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\HttpKernel;

class DateIntervalExtension extends \Twig_Extension
{
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

    private $supported = ['eng' => true, 'rus' => true];

    /**
     * @ignore
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
     * Получить интервал времени в строковом представлении
     * @param   string|\DateTime $date
     * @param   int              $max Ограничение на максимальное количество юнитов (например, 3 юнита: 4 года 6 месяцев 1 неделя)
     * @param   string           $separator Разделитель
     * @return  string
     * @throws  \Exception
     */
    public function getInterval($date, $max = 3, $separator = ' ') {
        if (!$date instanceof \DateTime) {
            if (!is_string($date)) {
                throw new \Exception('Expected formatted string of \DateTime object.');
            }
            $timestamp = $this->getTimestamp($date);
            $date = new \DateTime();
            $date->setTimestamp($timestamp);
        }

        return $this->getIntervalString($date, $max, $separator);
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
     * Получение строкового выражения времени
     * @param   \DateTime  $date
     * @param   int       $max Ограничение на максимальное количество юнитов (например, 3 юнита: 4 года 6 месяцев 1 неделя)
     * @param   string    $separator Разделитель
     * @return  string
     * @throws  \Exception
     */
    private function getIntervalString(\DateTime $date, $max, $separator)
    {
        $timeComponents = (array) $date->diff(new \DateTime());
        $notNullTimeComponents = array_values(array_filter(array_slice($timeComponents, 0, 6)));

        if(($max > 6) && ($max < 1) && !is_numeric($max)) {
            throw new \Exception('Max number of units should be a number between 1 and 6.');
        }
        $interval = [];
        for($i=0; $i<$max; $i++){
            // Если diff вернёт 0 лет мы должны исключить ненужные элементы из списка форм
            if(isset($notNullTimeComponents[$i])) {
                $formForNotNullComponent = array_values(array_slice($this->forms[$this->locale], -count($notNullTimeComponents)));
                $interval[] = $this->pluralRules($notNullTimeComponents[$i], $formForNotNullComponent[$i]);
            }
        }

        return implode($separator, $interval);
    }

    /**
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

    /**
     * @param $dateString
     * @return integer
     * @throws \Exception
     */
    private function getTimestamp($dateString)
    {
        if(!$this->isValidTimeStamp(strtotime($dateString))) {
            throw new \Exception('Not valid date string.');
        }

        return strtotime($dateString);
    }
}