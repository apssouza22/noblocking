<?php

sleep(10);
file_put_contents('./log-slow.txt', date('h:i:s'));
