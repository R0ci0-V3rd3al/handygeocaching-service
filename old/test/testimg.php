<?php

function directionString($direction) {
	//uhel na S,J,V,Z a kombinace
	$quadrants = Array('S', 'SV', 'V', 'JV', 'J', 'JZ', 'Z', 'SZ', '-');
	
	$direction+= 22.5;
	if ($direction >= 360)
		$direction -= 360;
	
	$quadrant = (int) ($direction / 45);
	
	return $quadrants[$quadrant];
}

function getImgData($data, $key = 'groundspeak') {
	$data = urldecode($data);
	
	for($i = 0; $i < strlen($data); $i++) {
		$y = $i % strlen($key);
		$data[$i] = $data[$i] ^ $key[$y];
	}
	
	return $data;
}

function getKey($data, $result) {
	$data = urldecode($data);
	
	for($i = 0; $i < strlen($result); $i++) {
		$result[$i] = $data[$i] ^ $result[$i];
	}
	
	return $result;
}

echo getImgData("VD_%5bWD%18%1d%19R_IBYD")."<br>"; // 160.9|NE
echo getImgData("VD_%5bWD%18%1d%19PSS%5cWM%5d")."<br>"; // 160.9|S
echo getImgData("W%5c_%40N%0f%1e%0cWSYIB%5eG")."<br>"; // 0.05 km|SW
echo getImgData("W%5c%5dLN%0f%1e%0cWQ%5dI%40XC")."<br>"; // 0.29 km|SW

echo directionString(359)."<br />";

echo getKey("YI%5eRL%1f%05%19WA%5bIC%5dQ", "0.03 km|")."<br>";
echo getKey("XQ%5eOUT%03%08%1aA%5bIC_V", "160.9 km|34.061")."<br>";
echo getImgData("CGW%5dA%07%19%14TUFAWG_", "signalthefrog")."<br>"; // 160.9|NE

//%0fYI%5eRL%1f%05%19WA%5bIC%5dQ 0.03 km|
//%0fYI%5cRL%1f%05%19TK%5eIJ%5cQ 0.29 km|


//$arr = Array(135,138,143,121,110,122,139); //1
//$arr = Array(135,138,143,117,112,118); //2

//for($i=0;$i<Count($arr);$i++) {
//	echo  chr($arr[$i]-ord($text[$i]));
//}
