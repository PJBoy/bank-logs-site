<?php
    function validateGetBank($bank)
    {
        if (!preg_match('/[89A-D][0-9A-F]$/', $bank) || $bank == "B8")
        {
            header('Location: index.html');
            exit;
        }
    }
    
    function translateBank($bank)
    {
        $regex_bank = $bank;
        $bank_val = intval($bank, 0x10);

        if (0x95 <= $bank_val && $bank_val <= 0x99)
        {
            $bank = '95..$99';
            $regex_bank = '9[5-9]';
        }
        elseif (0xB9 <= $bank_val && $bank_val < 0xBA)
        {
            $bank = 'B9..$BA';
            $regex_bank = '(?:B[9A])';
        }
        elseif (0xBA <= $bank_val && $bank_val < 0xC1)
        {
            $bank = 'BA..$C1';
            $regex_bank = '(?:B[A-F]|C[01])';
        }
        elseif (0xC1 <= $bank_val && $bank_val < 0xC2)
        {
            $bank = 'C1..$C2';
            $regex_bank = '(?:C[12])';
        }
        elseif (0xC2 <= $bank_val && $bank_val <= 0xCE)
        {
            $bank = 'C2..$CE';
            $regex_bank = '(?:C[2-9A-E])';
        }
        elseif (0xCF <= $bank_val && $bank_val <= 0xDE)
        {
            $bank = 'CF..$DE';
            $regex_bank = '(?:CF|D[0-9A-E])';
        }
        
        return array($bank, $regex_bank);
    }
    
    function loadCache($bank, $bankFilename)
    {
        $modificationTime = filemtime($bankFilename);
        $cacheFilename = "./cache/$bank!$modificationTime";
        
        if (isset($_GET['nocache']))
            unlink($cacheFilename);
        
        if (!isset($_GET['just']))
        {
            if (file_exists($cacheFilename))
            {
                readfile($cacheFilename);
                exit;
            }
            
            foreach (glob("./cache/$bank!*") as $path)
                unlink($path);
        }
    }

    function extractSection(&$bankBody)
    {
        // FIXME: won't work on multibank logs
        $i_address = strpos($bankBody, "\n\$$bank:{$_GET['just']}");
        if ($i_address === false)
        {
            preg_match_all("/^$regex_longROMAddress2.*/im", $bankBody, $results, PREG_OFFSET_CAPTURE);
            $i = 0;
            for (; $i < count($results[0]); ++$i)
                if (intval($results[2][$i][0], 0x10) >= intval($_GET['just'], 0x10))
                    break;
                
            if ($i == 0)
                exit;
            
            $i_address = $results[0][$i - 1][1];
        }

        $i_routineBegin_header = strrpos(substr($bankBody, 0, $i_address), ';;; ');
        if ($i_routineBegin_header === false)
            $i_routineBegin_header = 0;
        
        $i_routineBegin_noHeader = strrpos(substr($bankBody, 0, $i_address), "\n\n\n");
        if ($i_routineBegin_noHeader === false)
            $i_routineBegin_noHeader = 0;
        else
            $i_routineBegin_noHeader += 3;
        
        $i_routineBegin = max($i_routineBegin_header, $i_routineBegin_noHeader);

        $i_closingBrace = strpos($bankBody, "}\n\n\n", $i_address);
        if ($i_closingBrace === false)
            $i_closingBrace = strlen($bankBody);
        else
            $i_closingBrace += 1;
        
        $i_newRoutine = strpos($bankBody, "\n\n\n", $i_address);
        if ($i_newRoutine === false)
            $i_newRoutine = $i_closingBrace;
        
        $i_routineEnd = min($i_closingBrace, $i_newRoutine);
        
        $bankBody = substr($bankBody, $i_routineBegin, $i_routineEnd - $i_routineBegin);
        if ($i_routineBegin_header !== false)
            $routineHeader = substr($bankBody, 4, strpos($bankBody, " ;;;") - 4);
        
        return $routineHeader;
    }


    $bank = $_GET["bank"];
    validateGetBank($bank);
    list($bank, $regex_bank) = translateBank($bank);
    $bankFilename = "../ASM/ROM data/Super Metroid/Bank \$$bank.asm";
    if (!isset($_GET['debug']))
        loadCache($bank, $bankFilename);

    // Build regular expressions //
    // Addresses without dollar prefix
    $regex_shortROMAddress_raw = '([89A-F][0-9A-F]{3})\b';                         // 8000
    $regex_longROMAddressInBank_raw = "($regex_bank)$regex_shortROMAddress_raw";   // 808000 (current bank)
    $regex_longROMAddressInBank2_raw = "($regex_bank):$regex_shortROMAddress_raw"; // 80:8000 (current bank)
    $regex_longROMAddress_raw = "([89A-D][0-9A-F])$regex_shortROMAddress_raw";     // 808000 (any bank)
    $regex_longROMAddress2_raw = "([89A-D][0-9A-F]):$regex_shortROMAddress_raw";   // 80:8000 (any bank)

    // Addresses with dollar prefix
    $regex_shortROMAddress = "(?:^|(?<=[^#]))\\\$$regex_shortROMAddress_raw"; // $8000
    $regex_longROMAddressInBank = "\\\$$regex_longROMAddressInBank_raw";      // $808000 (current bank)
    $regex_longROMAddressInBank2 = "\\\$$regex_longROMAddressInBank2_raw";    // $80:8000 (current bank)
    $regex_longROMAddress = "\\\$$regex_longROMAddress_raw";                  // $808000 (any bank)
    $regex_longROMAddress2 = "\\\$$regex_longROMAddress2_raw";                // $80:8000 (any bank)

    // Address regex's suitable for syntax highlighting
    $regex_address_rom_raw = '((?:[8-9A-F][0-9A-F]:?)?[89A-F][0-9A-F]{3})\b';
    $regex_address_ram_raw = '((?:(?:7[0EF]:?[0-9A-F]|[0-7])[0-9A-F])?[0-9A-F]{2})\b(?!:)';
    $regex_address_rom = "\\\$$regex_address_rom_raw";
    $regex_address_ram = "\\\$$regex_address_ram_raw";

    function generateHeaderPanel($bankBody)
    {
        global $regex_shortROMAddress, $regex_longROMAddressInBank2;
        
        // Scrape all of the function declaration comments into $bankHeader and make them anchors
        preg_match_all("/;;; (?:$regex_shortROMAddress|$regex_longROMAddressInBank2).*?;;;/i", $bankBody, $bankHeader);
        
        // Indent header lines according to begin..end nesting (bank $A7 probably has the deepest nesting)
        $nestEnds = [];
        foreach ($bankHeader[0] as &$line)
        {
            // Don't think I'll ever need to do this for the multi-bank pages
            preg_match("/$regex_shortROMAddress/i", $line, $match);
            if (!$match)
                continue;
            
            $begin = intval($match[1], 0x10);
            
            while ($nestEnds && $nestEnds[0] < $begin)
                array_shift($nestEnds);
            
            $line = str_repeat(' ', count($nestEnds) * 4) . $line;
            
            preg_match("/$regex_shortROMAddress\\.\\.([0-9A-F]+)/i", $line, $match);
            if (!$match)
                continue;
            
            $begin = intval($match[1], 0x10);
            $end = intval($match[2], 0x10);
            if ($end < 0x100)
                $end += $begin & ~0xFF;
            
            $nestEnds[] = $end;
            sort($nestEnds, SORT_NUMERIC);
        }
        
        $bankHeader = implode("\n", $bankHeader[0]);
        
        // Turn into anchors
        $bankHeader = preg_replace("/(?<=;;; )$regex_shortROMAddress/i", '<a href="#f$1" class=address_rom>$$1</a>', $bankHeader);
        $bankHeader = preg_replace("/(?<=;;; )$regex_longROMAddressInBank2/i", '<a href="#f$1:$2">$$1:$2</a>', $bankHeader);
        
        return $bankHeader;
    }
    
    function generateBodyPanel(&$bankBody)
    {
        global $regex_shortROMAddress, $regex_longROMAddress, $regex_longROMAddressInBank2, $regex_longROMAddress2, $regex_address_ram, $regex_address_rom;
        
        // Create IDs for anchors to link to for function declaration headers
        $bankBody = preg_replace("/;;; $regex_shortROMAddress/i", ';;; <span id="f$1">$$1</span>', $bankBody);
        $bankBody = preg_replace("/;;; $regex_longROMAddressInBank2/i", ';;; <span id="f$1:$2">$$1:$2</span>', $bankBody);
        $bankBody = preg_replace("/^$regex_longROMAddressInBank2(.*?)\\n/im", '<div id="$2" class=addressed>$$1:$2$3</div>', $bankBody);
        if (isset($_GET['just']) && isset($_GET['highlight']))
            $bankBody = preg_replace("/<div id=\"{$_GET['just']}\"/i", "<div id=\"{$_GET['just']}\" class=highlighted", $bankBody);

        // Create sample generating onclick events
        $bankBody = preg_replace("/\[$regex_longROMAddress2\]/i", '<span class=clickable onclick="sample(this)">[$$1:$2]</span>', $bankBody);

        // Create anchors from any references to an address (typically branches and loads)
        $bankBody = preg_replace("/$regex_shortROMAddress/i", '<a href="#$1">$$1</a>', $bankBody);
        $bankBody = preg_replace("/$regex_longROMAddress/i", '<a href="$1#$2">$$1$2</a>', $bankBody);

        // But make function declaration headers anchor to itself
        $bankBody = preg_replace("/(;;; .*)<a href=\"#.*\">(.*)$regex_shortROMAddress(.*)<\/a>/i", '$1<a href="#f$3">$2$$3$4</a>', $bankBody);

        // Syntax highlighting //
        $bankBody = preg_replace('/(;.*?)(<\/div>|\n)/i', '<span class=comment>$1</span>$2', $bankBody);
        $bankBody = preg_replace("/$regex_address_rom/i", '<span class=address_rom>$$1</span>', $bankBody);

        $bankBody = preg_replace('/\b(ADC|AND|ASL|BC[CS]|BEQ|BIT|BMI|BNE|BPL|BR[AKL]|BV[CS]|CL[CDIV]|C[MO]P|CP[XY]|DE[CXY]|EOR|IN[CXY]|JM[LP]|JS[LR]|LD[AXY]|LSR|MV[NP]|NOP|ORA|PE[AIR]|P[HL][ABDPXY]|PHK|REP|RO[LR]|RT[ILS]|SBC|SE[CDIP]|ST[APXYZ]|TA[XY]|TC[DS]|TRB|TS[BCX]|TX[ASY]|TY[AX]|WAI|WDM|XBA|XCE)\b/i',
            '<span class="opcode">$1</span>', $bankBody);
        $bankBody = preg_replace('/\b(d[blwx])\b/i', '<span class=directive>$1</span>', $bankBody);
        $bankBody = preg_replace('/\|(.)(.)(.)(.)(.)(.)(.)(.)(?=\|)/i', '|<span class="gfx$1">$1</span><span class="gfx$2">$2</span><span class="gfx$3">$3</span><span class="gfx$4">$4</span><span class="gfx$5">$5</span><span class="gfx$6">$6</span><span class="gfx$7">$7</span><span class="gfx$8">$8</span>', $bankBody);

        // Hover descriptions of RAM addresses
        $bankBody = preg_replace("/(?<=[^#])$regex_address_ram/i", '<span class=address_ram onmouseenter="getRam(this);">$$1</span>', $bankBody);
    }

    $bankBody = str_replace("\r", '', file_get_contents($bankFilename));

    $routineHeader = "";
    if (isset($_GET['just']))
        $routineHeader = extractSection($bankBody);

    $bankHeader = generateHeaderPanel($bankBody);
    generateBodyPanel($bankBody);
    
    if (!isset($_GET['just']) && !isset($_GET['debug']))
        ob_start();
?>

<!-- thanks to somerando(caauyjdp) and yuriks for help with webdev -->
<!DOCTYPE html>
<html lang=en>
    <head>
        <meta charset=utf-8>
        <meta property=og:site_name content="PJ's bank logs">
        <meta property=og:title content="Bank $<?=$bank?>">
        <meta property=og:image content=http://patrickjohnston.org/snare.png>
        <?php if (isset($_GET['just'])): ?>
            <meta property=og:description content="<?=$routineHeader?>">
        <?php endif; ?>
        <meta name=theme-color content=#FF0000>
        <title>$<?=$bank?></title>
        <script async src=index.js></script>
        <link rel=stylesheet href=index.css>
    <?php if (isset($_GET['wrap'])): ?>
        <style>
            pre
            {
                white-space: pre-wrap;
            }
        </style>
    <?php endif; ?>
    </head>

    <body>
    <?php if (isset($_GET['just'])): ?>
        <pre><?=$bankBody?></pre>
    <?php else: ?>
        <div id=header>
            <a href=.>Index</a>
            &nbsp;&mdash;&nbsp;
            <a href=# onclick="toggleDarkMode(); return false;">Toggle dark mode</a>
            &nbsp;&mdash;&nbsp;
            <input id=search_input placeholder="Search regex" onchange="searchLogs(this.value);">
            <button type=button onclick="clearSearch();">Clear</button>
        </div>
        <div id=body>
            <div id=left class=comment><pre><?=$bankHeader?></pre></div>
            <div id=main_separator class=vertical_separator onpointerdown="return startSplitterDrag(event, this);"></div>
            <div id=right><pre><?=$bankBody?></pre></div>
        </div>
        <div id=main_search_separator class=horizontal_separator onpointerdown="return startSplitterDrag(event, this);"></div>
        <div id=search_results_panel style="height: 0;">
            <div id=search_left><pre id=search_results></pre></div>
            <div id=search_separator class=vertical_separator onpointerdown="return startSplitterDrag(event, this);"></div>
            <div id=search_right><iframe id=search_sample></iframe></div>
        </div>
    <?php endif; ?>
    </body>
</html>

<?php
    if (!isset($_GET['just']))
        file_put_contents($cacheFilename, ob_get_contents());
?>
