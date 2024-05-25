<?php
    $ramMap = [];
    $f = fopen('../ASM/Lists/Super Metroid/RAM map.asm', 'r');
    while (($line = fgets($f)) !== false)
    {
        if (!preg_match('/^\s*\$(?:(7[EF]):)?([0-9A-F]+)(:|\.\.).+/i', $line, $match))
            continue;
        
        $address = intval($match[2], 0x10);
        if ($match[1])
            $address |= intval($match[1], 0x10) << 0x10;
        else
            $address |= 0x7E0000;
        
        $ramMap[$address] = $match[0];
    }
    
    fclose($f);
    ksort($ramMap);
        
    $ramMapKeys = array_keys($ramMap);
    
    function address2Value($address_string)
    {
        return intval(implode('', explode(':', $address_string)), 16);
    }
    
    $ramAddress = address2Value($_GET["address"]) | 0x7E0000;
    $title = $ramMap[$ramMapKeys[count($ramMapKeys) - 1]];
    for ($i = 1, $i_end = count($ramMapKeys); $i < $i_end; ++$i)
        if ($ramMapKeys[$i] > $ramAddress)
        {
            $title = $ramMap[$ramMapKeys[$i - 1]];
            break;
        }
        
    echo $title;
?>
