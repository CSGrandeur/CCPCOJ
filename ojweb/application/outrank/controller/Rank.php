<?php

namespace app\outrank\controller;

class Rank extends Outrankbase {
    function rank() {
        return $this->fetch();
    }
}