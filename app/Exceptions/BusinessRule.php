<?php

namespace App\Exceptions;

use RuntimeException;

/** A business rule refused the action. The message is safe to show to the user. */
class BusinessRule extends RuntimeException {}
