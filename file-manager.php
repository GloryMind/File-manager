<?php

class FileManager {
	private $path;
	private $handle;
	private $offset;
	public function __construct( $path ) {
		$this->path = $path;
	}
	
	public function Open() {
		if ( false === $this->handle = @fopen($this->path , "c+") ) {
			$this->riseError( "Fopen error: '{$this->path}'" );
		}
		return $this;
	}
	
	private $is_lock = false;
	public function Lock( $wait = true ) {
		if ( $this->is_lock ) { return $this; }
		if ( !flock($this->handle , LOCK_EX|($wait?0:LOCK_UN)) ) {
			if ( !$wait ) { return $this; }
			$this->riseError( "Flock error{LOCK_EX}" );
		}
		$this->is_lock = true;
		return $this;
	}
	public function UnLock() {
		if ( !$this->is_lock ) { return $this; }
		if ( !flock($this->handle , LOCK_UN) ) {
			$this->riseError( "Flock error{LOCK_UN}" );
		}
		
		$this->is_lock = false;
		return $this;
	}
	public function IsLock() {
		if ( $this->is_lock ) { return true; }
		$this->Lock(false);
		if ( !$this->is_lock ) { return false; }
		$this->UnLock();
		return true;
	}
	public function Read($offset = null, $size = null) {
		if ( $size === null ) {
			$size = $offset;
			$offset = $this->GetPosition();
		}
		if ( $size === null ) {
			$size = $this->GetSize() - $offset;
		}
		$this->SetPosition( $offset );
		if ( false === $buf = fread($this->handle, $size) ) {
			$this->riseError("Fread error");
		}
		$this->AddPosition( strlen($buf) );
		return $buf;
	}
	public function Write($offset, $data = null) {
		if ( $data === null ) {
			$data = $offset;
			$offset = $this->GetPosition();
		}
		$this->SetPosition( $offset );
		if ( fwrite($this->handle, $data) !== strlen($data) ) {
			$this->riseError("Fwrite error(data length ".strlen($data).")");
		}
		$this->SetPosition( $offset + strlen($data) );
		
		return $this;
	}
	public function GetSize() {
		if ( !( $stat = fstat($this->handle) ) ) {
			$this->riseError("Fstat error");
		}
		return $stat['size'];
	}
	public function SetSize( $size = 0 ) {
		if ( ftruncate($this->handle, $size ) === false ) {
			$this->riseError("Ftruncate error");
		}
		return $this;
	}
	public function GetPosition() {
		if ( false === $pos = ftell($this->handle) ) {
			$this->riseError("Ftell error");
		}
		return $pos;
	}
	public function SetPosition( $offset ) {
		if ( @fseek($this->handle, $offset) !== 0 ) {
			$this->riseError("Fseek {$offset} error");
		}
		return $this;
	}
	public function AddPosition( $offset ) { $this->SetPosition( $this->GetPosition() + $offset ); return $this; }
	public function DddPosition( $offset ) { $this->SetPosition( $this->GetPosition() - $offset ); return $this; }
	public function Close() {
		if ( $this->handle ) {
			if ( @fclose($this->handle) === false ) {
				$this->riseError("Fclose error");
			}
			$this->handle = null;
		}
		return $this;
	}

	private $riseErrors = [];
	private function riseError( $text , $closeFile = true ) {
		static $recLv = 0;
		$recLv++;

		$this->riseErrors[] = "File error.\r\n{$text}\r\nPath: '{$this->path}\r\n";
		
		if ( $closeFile ) { $this->Close(); }

		if ( $recLv === 1 ) {
			$recLv = 0;
			throw new \Exception( implode('',$this->riseErrors) );
		}
		
		$recLv--;
	}
	
	public function GetHandle() {
		return $this->handle;
	}
}