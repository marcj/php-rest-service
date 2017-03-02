<?php

namespace Test\Synthetic;

use RestService\Server;
use Test\Controller\MyRoutes;

class UnicodeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Server
     */
    private $restService;

    public function setUp()
    {
        $this->restService = Server::create('/', new MyRoutes)
            ->setClient('RestService\\InternalClient')
            ->collectRoutes();
    }
	
	private function assertEcho($test_string){
		$response = $this->restService->simulateCall("/echo?text=$test_string", 'post');
		$this->assertEquals("{
    \"status\": 200,
    \"data\": \"$test_string\"
}", $response);
	}
	
    public function testUnicode()
    {
        $this->assertEcho('ru: В чащах юга жил бы цитрус? Да, но фальшивый экземпляр!');
    	$this->assertEcho('da: Quizdeltagerne spiste jordbær med fløde, mens cirkusklovnen Wolther spillede på xylofon');
        $this->assertEcho('de: Falsches Üben von Xylophonmusik quält jeden größeren Zwerg');
        $this->assertEcho('el: Γαζέες καὶ μυρτιὲς δὲν θὰ βρῶ πιὰ στὸ χρυσαφὶ ξέφωτο');
        $this->assertEcho('es: El pingüino Wenceslao hizo kilómetros bajo exhaustiva lluvia y frío, añoraba a su querido cachorro.');
        $this->assertEcho('fr: Le cœur déçu mais l\'âme plutôt naïve, Louÿs rêva de crapaüter en canoë au delà des îles, près du mälström où brûlent les novæ.');
        $this->assertEcho('ga: D\'fhuascail Íosa, Úrmhac na hÓighe Beannaithe, pór Éava agus Ádhaimh');
        $this->assertEcho('hu: Árvíztűrő tükörfúrógép');
        $this->assertEcho('is: Kæmi ný öxi hér ykist þjófum nú bæði víl og ádrepa');
        $this->assertEcho('jp1: いろはにほへとちりぬるを わかよたれそつねならむ うゐのおくやまけふこえて あさきゆめみしゑひもせす');
        $this->assertEcho('jp2: イロハニホヘト チリヌルヲ ワカヨタレソ ツネナラム ウヰノオクヤマ ケフコエテ アサキユメミシ ヱヒモセスン');
        $this->assertEcho('iw: דג סקרן שט בים מאוכזב ולפתע מצא לו חברה איך הקליטה');
        $this->assertEcho('pl: Pchnąć w tę łódź jeża lub ośm skrzyń fig');
        $this->assertEcho('th: ๏ เป็นมนุษย์สุดประเสริฐเลิศคุณค่า กว่าบรรดาฝูงสัตว์เดรัจฉาน จงฝ่าฟันพัฒนาวิชาการ อย่าล้างผลาญฤๅเข่นฆ่าบีฑาใคร ไม่ถือโทษโกรธแช่งซัดฮึดฮัดด่า หัดอภัยเหมือนกีฬาอัชฌาสัย ปฏิบัติประพฤติกฎกำหนดใจ พูดจาให้จ๊ะๆ จ๋าๆ น่าฟังเอย ฯ');
        $this->assertEcho('tr: Pijamalı hasta, yağız şoföre çabucak güvendi');
    }
}