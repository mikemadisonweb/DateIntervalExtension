<?php

namespace Madison\Twig;

/**
 * Class DateIntervalExtensionTest
 * @package Madison\Twig\Extensions
 */
class DateIntervalExtensionTest extends \PHPUnit_Framework_TestCase {

    /**
     * @param string $template
     * @return \Twig_Environment
     */
    private function buildEnv($template) {
        $loader = new \Twig_Loader_Array(array(
            'template' => $template,
        ));
        $twig = new \Twig_Environment($loader);
        $twig->addExtension(new DateIntervalExtension());
        return $twig;
    }

    /**
     * @param string $template
     * @param array $context
     * @return string
     */
    private function process($template, $context) {
        $twig = $this->buildEnv($template);
        $result = $twig->render('template', $context);
        return $result;
    }

    /**
     * @param int $expected
     * @param string $template
     * @param array $context
     * @internal param string $input
     */
    private function check($expected, $template, $context) {
        $result = $this->process($template, $context);
        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider provider
     * @param int|string $input
     * @param int $output
     * @param string $template
     */
    public function testFileSize($input, $output, $template) {
        $this->check($output, $template, ['input' => $input]);
    }

    /**
     * @return array
     */
    public function provider()
    {
        return [
            ['28.06.1986', '27 years', '{{ input|age }}'],
        ];
    }
}