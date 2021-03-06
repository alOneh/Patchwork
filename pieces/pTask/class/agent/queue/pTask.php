<?php /***** vi: set encoding=utf-8 expandtab shiftwidth=4: ****************
 *
 *   Copyright : (C) 2011 Nicolas Grekas. All rights reserved.
 *   Email     : p@tchwork.org
 *   License   : http://www.gnu.org/licenses/agpl.txt GNU/AGPL
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU Affero General Public License as
 *   published by the Free Software Foundation, either version 3 of the
 *   License, or (at your option) any later version.
 *
 ***************************************************************************/


class agent_queue_pTask extends agent
{
    const contentType = 'image/gif';


    public

    $get = array(
        '__1__:i:1',
        '__2__:c:[-_0-9a-zA-Z]{32}'
    );


    protected

    $maxage = -1,

    $lock,
    $queueName = 'queue',
    $queueFolder = 'data/queue/pTask/',
    $dual = 'pTask',

    $sqlite;


    function control()
    {
        $d = $this->dual;
        $d = $this->dual = new $d;
        $this->sqlite = $d->getSqlite();

        if (!empty($this->get->__1__))
        {
            $id = $this->get->__1__;

            if (!empty($this->get->__2__) && $this->get->__2__ == $this->getToken())
            {
                if ($this->getLock())
                {
                    ob_start(array($this, 'ob_handler'));
                    $this->doOne($id);
                    ob_end_flush();
                }
                else $this->doAsap($id);

                return;
            }
            else $this->touchOne($id);
        }

        $this->queueNext();
    }

    function compose($o)
    {
        echo base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');
    }

    function ob_handler($buffer)
    {
        $this->releaseLock();
        $this->queueNext();

        '' !== $buffer && W($buffer);

        return '';
    }

    protected function queueNext()
    {
        $time = time();
        $sql = "SELECT OID, base, run_time FROM queue WHERE run_time>0 ORDER BY run_time, OID LIMIT 1";
        if ($data = $this->sqlite->arrayQuery($sql, SQLITE_ASSOC))
        {
            $data = $data[0];

            0 > $this->maxage && $this->maxage = $CONFIG['maxage'];

            if ($data['run_time'] <= $time)
            {
                $sql = "UPDATE queue SET run_time=0
                        WHERE OID={$data['OID']} AND run_time>0";
                $this->sqlite->queryExec($sql);

                $this->sqlite->changes() && tool_url::touch("{$data['base']}queue/pTask/{$data['OID']}/" . $this->getToken());

                $sql = "SELECT run_time FROM queue WHERE run_time>{$time} ORDER BY run_time LIMIT 1";
                if ($data = $this->sqlite->arrayQuery($sql, SQLITE_NUM)) patchwork::setMaxage(min($this->maxage, $data[0][0] - $time));
            }
            else patchwork::setMaxage(min($this->maxage, $data['run_time'] - $time));
        }
    }

    protected function doAsap($id)
    {
        $sql = "UPDATE queue SET run_time=1
                WHERE OID={$id} AND run_time=0";
        $this->sqlite->queryExec($sql);
    }

    protected function doOne($id)
    {
        $sqlite = $this->sqlite;

        $sql = "SELECT data FROM queue WHERE OID={$id} AND run_time=0";
        $data = $sqlite->arrayQuery($sql);

        if (!$data) return;

        $data_serialized = $data[0][0];
        $data = unserialize($data_serialized);

        $this->restoreContext($data['cookie'], $data['session']);

        try
        {
            try
            {
                if (0 < $time = (int) $data['task']->getNextRun())
                {
                    $sql = time();
                    if ($time < $sql - 366*86400) $time += $sql;

                    $sql = "UPDATE queue SET run_time={$time} WHERE OID={$id}";
                    $sqlite->queryExec($sql);
                }
            }
            catch (Exception $e)
            {
                $data['task']->run();
                throw $e;
            }

            $data['task']->run();
        }
        catch (Exception $e)
        {
            echo "Exception on pTask #{$id}:\n\n";
            print_r($e);
            $time = false;
        }

        if ($time > 0)
        {
            $data['session'] = class_exists('SESSION', false) ? SESSION::getAll() : array();

            if ($data_serialized !== $data = serialize($data))
            {
                $data = sqlite_escape_string($data);
                $sql = "UPDATE queue SET data='{$data}' WHERE OID={$id}";
                $sqlite->queryExec($sql);
            }
        }
        else if (false !== $time)
        {
            $sql = "DELETE FROM queue WHERE OID={$id}";
            $sqlite->queryExec($sql);
        }
    }

    protected function touchOne($id)
    {
    }

    protected function restoreContext(&$cookie, &$session)
    {
        if ($session)
        {
            $_COOKIE = array();
            foreach ($session as $k => &$v) s::set($k, $v);
            SESSION::regenerateId(false, false);
        }

        $_COOKIE =& $cookie;
    }

    protected function getLock()
    {
        $lock = patchworkPath($this->queueFolder) . $this->queueName . '.lock';

        if (!file_exists($lock))
        {
            touch($lock);
            chmod($lock, 0666);
        }

        $this->lock = $lock = fopen($lock, 'wb');
        flock($lock, LOCK_EX+LOCK_NB, $wb) || $wb = true;

        if ($wb)
        {
            fclose($lock);
            return false;
        }

        set_time_limit(0);

        return true;
    }

    protected function releaseLock()
    {
        fclose($this->lock);
    }

    protected function getToken()
    {
        $token = patchworkPath($this->queueFolder) . $this->queueName . '.token';

        //XXX user right problem?
        file_exists($token) || file_put_contents($token, patchwork::strongid());

        return trim(file_get_contents($token));
    }
}
