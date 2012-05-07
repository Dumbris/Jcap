<?php
// Jcap — Simplified captcha generator (based on kcaptcha project version 2.0: http://captcha.ru/kcaptcha/)
//   Copyleft 2011, Bohdan <jeteir@gmail.com>


// ***** ORIGINAL DISCLAIMER *****
// 
// KCAPTCHA PROJECT VERSION 2.0
// 
// Automatic test to tell computers and humans apart
// 
// Copyright by Kruglov Sergei, 2006, 2007, 2008, 2011
// www.captcha.ru, www.kruglov.ru
// 
// System requirements: PHP 4.0.6+ w/ GD
// 
// KCAPTCHA is a free software. You can freely use it for developing own site or software.
// If you use this software as a part of own sofware, you must leave copyright notices intact or add KCAPTCHA copyright notices to own.
// As a default configuration, KCAPTCHA has a small credits text at bottom of CAPTCHA image.
// You can remove it, but I would be pleased if you left it. ;)
// 
// See kcaptcha_config.php for customization
// 
// ***** ORIGINAL DISCLAIMER *****


interface iJcap
{
	public function generate();
	public function getKeyString();
	public function save($fileName = null, $noHeaders = false);
	public function setBlackNoiseDensity($density);
	public function setWaveAmplitude($amplitude);
	public function setWhiteNoiseDensity($density);
}

class Jcap implements iJcap
{
	protected $allowedAlphabet = '23456789abcdegikpqsvxyz';
	protected $alphabet = '0123456789abcdefghijklmnopqrstuvwxyz';

	protected $blackDensity = 0.0333;

	protected $captcha;

	protected $captchaHeight = 80;
	protected $captchaWidth = 160;

	protected $font;
	protected $fontHeight;
	protected $fontMetrics;
	protected $fontWidth;

	protected $keyString;
	protected $length;

	protected $verticalAmplitude = 8;

	protected $whiteDensity = 0.1666;

	public function __construct($min = 5, $max = 7)
	{
		$this->length = rand($min, $max);

		while (true) {
			$this->keyString = null;

			for ($i = 0; $i < $this->length; $i++)
				$this->keyString .= $this->allowedAlphabet[mt_rand(0, strlen($this->allowedAlphabet) - 1)];

			if (0 == preg_match('/cp|cb|ck|c6|c9|rn|rm|mm|co|do|cl|db|qp|qb|dp|ww/', $this->keyString))
				break;
		}
	}

	public function __destruct()
	{
        if(isset($this->captcha))
        {
            imagedestroy($this->captcha);
        }

        if(isset($this->result))
        {
            imagedestroy($this->result);
        }

	}

	public function generate()
	{
		do {
			$x = $this->drawText();
		} while ($x >= $this->captchaWidth - 10);

		$this->fillNoise($x);

		$this->fillWave($x / 2);
	}

	public function getKeyString()
	{
		return $this->keyString;
	}

	public function save($fileName = null, $noHeaders = false)
	{
		if (null != $fileName) {
			imagepng($this->result, $fileName);

			return;
		}

		if (false == $noHeaders) {
			header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); 
			header('Cache-Control: no-store, no-cache, must-revalidate'); 
			header('Cache-Control: post-check=0, pre-check=0', FALSE); 
			header('Pragma: no-cache');
			header("Content-Type: image/x-png");
		}

		imagepng($this->result);
	}

	public function setBlackNoiseDensity($density)
	{
		$this->blackDensity = $density;
	}

	public function setWaveAmplitude($amplitude)
	{
		$this->verticalAmplitude = $amplitude;
	}

	public function setWhiteNoiseDensity($density)
	{
		$this->whiteDensity = $density;
	}

	protected function drawText()
	{
		$this->loadFont();

		$this->captcha = imagecreatetruecolor($this->captchaWidth, $this->captchaHeight);

		imagealphablending($this->captcha, true);

		$white = imagecolorallocate($this->captcha, 255, 255, 255);
		$black = imagecolorallocate($this->captcha, 0, 0, 0);

		imagefilledrectangle($this->captcha, 0, 0, $this->captchaWidth - 1, $this->captchaHeight - 1, $white);

		$x = 1;

		$odd = mt_rand(0,1);

		if (0 == $odd)
			$odd = -1;

		for ($i = 0; $i < $this->length; $i++) {
			$m = $this->fontMetrics[$this->keyString[$i]];

			$y = (($i % 2) * $this->verticalAmplitude - $this->verticalAmplitude / 2) * $odd;
			$y += mt_rand(-round($this->verticalAmplitude / 3), round($this->verticalAmplitude / 3));
			$y += ($this->captchaHeight - $this->fontHeight) / 2;

			$shift = 0;

			if ($i > 0) {
				$shift = 10000;

				for ($sy = 3; $sy < $this->fontHeight - 10; $sy += 1) {
					for ($sx = $m[0] - 1; $sx < $m[1]; $sx += 1) {
				        	$rgb = imagecolorat($this->font, $sx, $sy);

			        		$opacity= $rgb >> 24;

						if ($opacity < 127) {
							$left = $sx - $m[0] + $x;

							$py = $sy + $y;

							if ($py > $this->captchaHeight)
								break;

							for ($px = min($left, $this->captchaWidth - 1); $px > $left - 200 && $px >= 0; $px -= 1) {
					        		$color = imagecolorat($this->captcha, $px, $py) & 0xff;

								if ($color + $opacity < 170) {
									if($shift > $left - $px)
										$shift = $left - $px;

									break;
								}
							}

							break;
						}
					}
				}

				if ($shift == 10000)
					$shift = mt_rand(4, 6);

			}

			imagecopy($this->captcha, $this->font, $x - $shift, $y, $m[0], 1, $m[1] - $m[0], $this->fontHeight);

			$x += $m[1] - $m[0] - $shift;
		}

		return $x;
	}

	protected function fillNoise($x)
	{
		$white = imagecolorallocate($this->font, 255, 255, 255);
		$black = imagecolorallocate($this->font, 0, 0, 0);

		for ($i = 0; $i < (($this->captchaHeight - 30) * $x) * $this->whiteDensity; $i++)
			imagesetpixel($this->captcha, mt_rand(0, $x - 1), mt_rand(10, $this->captchaHeight - 15), $white);

		for ($i = 0; $i < (($this->captchaHeight - 30) * $x) * $this->blackDensity; $i++)
			imagesetpixel($this->captcha, mt_rand(0, $x - 1), mt_rand(10, $this->captchaHeight - 15), $black);
		
	}

	protected function fillWave($center)
	{
		$rand1=mt_rand(750000, 1200000) / 10000000;
		$rand2=mt_rand(750000, 1200000) / 10000000;
		$rand3=mt_rand(750000, 1200000) / 10000000;
		$rand4=mt_rand(750000, 1200000) / 10000000;
		$rand5=mt_rand(0, 31415926) / 10000000;
		$rand6=mt_rand(0, 31415926) / 10000000;
		$rand7=mt_rand(0, 31415926) / 10000000;
		$rand8=mt_rand(0, 31415926) / 10000000;
		$rand9=mt_rand(330, 420) / 110;
		$rand10=mt_rand(330, 450) / 100;

		$this->result = imagecreatetruecolor($this->captchaWidth, $this->captchaHeight);

		$foreground_color = array(mt_rand(0,80), mt_rand(0,80), mt_rand(0,80));
		$background_color = array(mt_rand(220,255), mt_rand(220,255), mt_rand(220,255));

		$foreground = imagecolorallocate($this->result, $foreground_color[0], $foreground_color[1], $foreground_color[2]);
		$background = imagecolorallocate($this->result, $background_color[0], $background_color[1], $background_color[2]);

		imagefilledrectangle($this->result, 0, 0, $this->captchaWidth - 1, $this->captchaHeight - 1, $background);
		imagefilledrectangle($this->result, 0, $this->captchaHeight, $this->captchaWidth-1, $this->captchaHeight, $foreground);

		for ($x=0; $x < $this->captchaWidth; $x++) {
			for ($y=0; $y < $this->captchaHeight; $y++) {
				$sx = $x + (sin($x * $rand1 + $rand5) + sin($y * $rand3 + $rand6)) * $rand9 - $this->captchaWidth / 2 + $center + 1;
				$sy = $y + (sin($x * $rand2 + $rand7) + sin($y * $rand4 + $rand8)) * $rand10;

				if ($sx < 0 || $sy < 0 || $sx >= $this->captchaWidth - 1 || $sy >= $this->captchaHeight - 1)
					continue;
				else {
					$color = imagecolorat($this->captcha, $sx, $sy) & 0xFF;
					$color_x = imagecolorat($this->captcha, $sx + 1, $sy) & 0xFF;
					$color_y = imagecolorat($this->captcha, $sx, $sy + 1) & 0xFF;
					$color_xy = imagecolorat($this->captcha, $sx + 1, $sy + 1) & 0xFF;
				}

				if ($color == 255 && $color_x == 255 && $color_y == 255 && $color_xy == 255)
					continue;
				else if ($color == 0 && $color_x == 0 && $color_y == 0 && $color_xy == 0) {
					$newred = $foreground_color[0];
					$newgreen = $foreground_color[1];
					$newblue = $foreground_color[2];
				} else {
					$frsx = $sx - floor($sx);
					$frsy = $sy - floor($sy);
					$frsx1 = 1 - $frsx;
					$frsy1 = 1 - $frsy;

					$newcolor=(
						$color * $frsx1 * $frsy1 +
						$color_x * $frsx * $frsy1 +
						$color_y * $frsx1 * $frsy +
						$color_xy * $frsx * $frsy);

					if ($newcolor > 255)
						$newcolor = 255;

					$newcolor = $newcolor / 255;
					$newcolor0 = 1 - $newcolor;

					$newred = $newcolor0 * $foreground_color[0] + $newcolor * $background_color[0];
					$newgreen = $newcolor0 * $foreground_color[1] + $newcolor * $background_color[1];
					$newblue = $newcolor0 * $foreground_color[2] + $newcolor * $background_color[2];
				}

				imagesetpixel($this->result, $x, $y, imagecolorallocate($this->result, $newred, $newgreen, $newblue));
			}
		}
	}

	protected function loadFont()
	{
		$fonts = JcapFonts::getFonts();
		$metrics = JcapFonts::getMetrics();

		$choice = rand(0, count($fonts) - 1);

		$this->font = imagecreatefromstring(base64_decode($fonts[$choice]));

		$this->fontHeight = imagesy($this->font) - 1;
		$this->fontWidth = imagesx($this->font);

		$this->fontMetrics = $metrics[$choice];
	}
}

class JcapFonts
{
	private static $fonts = array(
		'
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
	'
	);

	private static $metrics = array(
		array(
                        '0' => array(6, 29),
                        '1' => array(38, 60),
                        '2' => array(70, 93),
                        '3' => array(102, 124),
                        '4' => array(133, 157),
                        '5' => array(166, 188),
                        '6' => array(198, 221),
                        '7' => array(231, 254),
                        '8' => array(262, 285),
                        '9' => array(294, 317),
                        'a' => array(326, 349),
                        'b' => array(357, 385),
                        'c' => array(396, 416),
                        'd' => array(426, 454),
                        'e' => array(463, 485),
                        'f' => array(495, 513),
                        'g' => array(541, 567),
                        'h' => array(575, 604),
                        'i' => array(612, 627),
                        'j' => array(654, 667),
                        'k' => array(679, 709),
                        'l' => array(717, 732),
                        'm' => array(740, 783),
                        'n' => array(792, 821),
                        'o' => array(830, 855),
                        'p' => array(864, 892),
                        'q' => array(903, 931),
                        'r' => array(939, 958),
                        's' => array(967, 986),
                        't' => array(995, 1011),
                        'u' => array(1038, 1067),
                        'v' => array(1075, 1103),
                        'w' => array(1110, 1151),
                        'x' => array(1159, 1183),
                        'y' => array(1190, 1218),
                        'z' => array(1225, 1249)
		),
		array(
                        '0' => array(5, 28),
                        '1' => array(39, 54),
                        '2' => array(67, 88),
                        '3' => array(98, 117),
                        '4' => array(127, 150),
                        '5' => array(159, 179),
                        '6' => array(189, 211),
                        '7' => array(219, 241),
                        '8' => array(250, 271),
                        '9' => array(280, 302),
                        'a' => array(310, 333),
                        'b' => array(341, 367),
                        'c' => array(377, 399),
                        'd' => array(408, 436),
                        'e' => array(445, 466),
                        'f' => array(475, 496),
                        'g' => array(516, 541),
                        'h' => array(550, 577),
                        'i' => array(586, 599),
                        'j' => array(624, 637),
                        'k' => array(649, 676),
                        'l' => array(684, 698),
                        'm' => array(706, 746),
                        'n' => array(754, 781),
                        'o' => array(791, 816),
                        'p' => array(825, 852),
                        'q' => array(862, 889),
                        'r' => array(897, 917),
                        's' => array(926, 942),
                        't' => array(951, 968),
                        'u' => array(995, 1022),
                        'v' => array(1029, 1055),
                        'w' => array(1062, 1101),
                        'x' => array(1108, 1136),
                        'y' => array(1144, 1169),
                        'z' => array(1177, 1199),
		),
		array(
                        '0' => array(6, 29),
                        '1' => array(40, 59),
                        '2' => array(70, 92),
                        '3' => array(101, 124),
                        '4' => array(134, 156),
                        '5' => array(166, 189),
                        '6' => array(198, 221),
                        '7' => array(230, 253),
                        '8' => array(262, 285),
                        '9' => array(294, 317),
                        'a' => array(327, 350),
                        'b' => array(358, 383),
                        'c' => array(393, 413),
                        'd' => array(422, 448),
                        'e' => array(457, 477),
                        'f' => array(486, 506),
                        'g' => array(530, 553),
                        'h' => array(561, 587),
                        'i' => array(595, 609),
                        'j' => array(633, 648),
                        'k' => array(660, 688),
                        'l' => array(694, 708),
                        'm' => array(716, 756),
                        'n' => array(765, 790),
                        'o' => array(799, 822),
                        'p' => array(831, 856),
                        'q' => array(866, 892),
                        'r' => array(901, 922),
                        's' => array(930, 947),
                        't' => array(956, 972),
                        'u' => array(1000, 1025),
                        'v' => array(1033, 1058),
                        'w' => array(1065, 1101),
                        'x' => array(1109, 1133),
                        'y' => array(1140, 1166),
                        'z' => array(1173, 1194),
		)
	);

	public static function getFonts()
	{
		return self::$fonts;
	}

	public static function getMetrics()
	{
		return self::$metrics;
	}
}
