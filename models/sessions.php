<?php
class Sessions extends Database {
  protected static $table           = 'sessions';
  protected static $primary_key     = 'session_id';
  protected static $timestamps      = true;
  protected static $timestamps_type = 'timestamp';
}
?>