<?php
declare(strict_types=1);
/**
 * Manja Repository Model - an abstract model of data in typical repository scheme on manja server
 *
 * Notes:
 *    node ID syntax:
 *      d<int> 			Document in Manja Repository
 *      <int>			Folder in Manja Repository
 *
 * @package ManjaRepositoryModel
 * @copyright 2008-2021 IT-Service Robert Frunzke
 */


/**
 * Common manja repository model exception
 */
class MjCError extends mjError {}
/**
 * Invalid Argument exception in manja repository model
 */
class MjCInvalidArgumentError extends MjCError {}
/**
 * Object not found exception in manja repository model
 */
class MjCObjectNotFoundException extends MjCError {}
/**
 * Object exists exception in manja repository model
 */
class MjCObjectExistsAtTargetException extends MjCError {}
/**
 * Not supported exception in manja repository model
 */
class MjCNotSupportedException extends MjCError {}
/**
 * Constraint exception in manja repository model
 */
/*UNUSED: class MjCConstraintException extends MjCError {} */

