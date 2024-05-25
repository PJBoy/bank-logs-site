<?php
    // Build regular expressions //
    $regex_shortROMAddress_raw = '([89A-F][0-9A-F]{3})\b';                       // 8000
    $regex_longROMAddress2_raw = "([89A-D][0-9A-F]):$regex_shortROMAddress_raw"; // 80:8000 (any bank)
    $regex_longROMAddress2 = "\\\$$regex_longROMAddress2_raw";                   // $80:8000 (any bank)
    
    $regex = $_GET["regex"];
    $ret = [];
    foreach (glob("../ASM/ROM data/Super Metroid/Bank*.asm") as $bankFilepath)
    {
        $bankContents = str_replace("\r", '', file_get_contents($bankFilepath));
        
        preg_match_all("/$regex_longROMAddress2.*/i", $bankContents, $results, PREG_OFFSET_CAPTURE);
        $results = $results[0];
        $bankAnchorOffsets = [];
        foreach ($results as $r)
            $bankAnchorOffsets[] = $r[1];
        
        preg_match_all("/.*$regex.*/i", $bankContents, $results, PREG_OFFSET_CAPTURE);
        $results = $results[0];
        if (!$results)
            continue;
        
        foreach ($results as $r)
        {
            $text = $r[0];
            preg_match("/$regex_longROMAddress2/", $text, $match);
            if (!$match)
            {
                $i = 0;
                for (; $i < count($bankAnchorOffsets); ++$i)
                    if ($bankAnchorOffsets[$i] >= $r[1])
                        break;
                
                if ($i == 0)
                    continue;
                
                preg_match("/$regex_longROMAddress2/", substr($bankContents, $bankAnchorOffsets[$i - 1]), $match);
            }
            
            $bank = $match[1];
            $address = $match[2];
            $ret[] = [
                'bank' => $bank,
                'address' => $address,
                'text' => $text
            ];
            
            if (count($ret) >= 1024)
                break;
        }
            
        if (count($ret) >= 1024)
            break;
    }
    
    echo json_encode($ret, JSON_PRETTY_PRINT);
?>
