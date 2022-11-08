<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

class StrankyWebuTest extends AbstractWebTest
{

    /**
     * @test
     * @dataProvider provideWebUrls
     * @param string[] $urls
     */
    public function Muzu_si_zobrazit_kazdou_stranku_na_webu(...$urls) {
        $this->testPagesAccessibility($urls);
    }

    public function provideWebUrls(): array {

        return [
            'základní'    => [
                basename(__DIR__ . '/../../web'),
                basename(__DIR__ . '/../../admin'),
            ],
            'moduly webu' => $this->getUrlsModuluWebu(),
//            'moduly adminu' => $this->getUrlsModuluWebu(),
        ];
    }

    protected function getUrlsModuluWebu(): array {
        $modulyWebu         = scandir(__DIR__ . '/../../web/moduly');
        $modulyWebuBaseUrls = [];
        foreach ($modulyWebu as $modulWebu) {
            if (!preg_match('~[.]php$~', $modulWebu)) {
                continue;
            }
            $modulyWebuBaseUrls[] = basename($modulWebu, '.php');
        }
        $webBaseUrl = basename(__DIR__ . '/../../web');
        return array_map(static function (string $modulWebuBaseurl) use ($webBaseUrl) {
            return $webBaseUrl . '/' . $modulWebuBaseurl;
        }, $modulyWebuBaseUrls);
    }

    protected function getUrlsModuluAdminu(): array {
        $modulyWebu         = scandir(__DIR__ . '/../../admin/scripts/modules');
        $modulyWebuBaseUrls = [];
        foreach ($modulyWebu as $modulWebu) {
            if (!preg_match('~([.]php)(^[a-z-]+)$~', $modulWebu)) {
                continue;
            }
            $modulyWebuBaseUrls[] = basename($modulWebu, '.php');
        }
        $webUrl = basename(__DIR__ . '/../../web');
        return array_map(static function (string $modulWebuBaseurl) use ($webUrl) {
            return $webUrl . '/' . $modulWebuBaseurl;
        }, $modulyWebuBaseUrls);
    }
}
