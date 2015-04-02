<?php

namespace PeskyORM\Object;

use PeskyORM\DbObject;

/**
 * Class User
 * @package PeskyORM\Object
 *
 * @property-read UserToken[] $UserToken
 *
 * @property-read int       $id
 * @property-read string    $email
 * @property-read string    $password
 * @property-read bool      $confirmed
 * @property-read string    $created
 * @property-read string    $created_date
 * @property-read string    $created_time
 * @property-read string    $updated
 * @property-read string    $updated_date
 * @property-read string    $updated_time
 * @property-read int       $storage_total
 * @property-read int       $storage_used
 * @property-read string    $first_name
 * @property-read string    $last_name
 * @property-read string    $mid_name
 * @property-read string    $nickname
 * @property-read string    $gender
 * @property-read string    $signature
 * @property-read string    $badge
 * @property-read string    $region
 * @property-read string    $language
 * @property-read bool      $cycle_recording
 * @property-read string    $insurance_company_alias
 * @property-read string    $insurance_contract_id
 * @property-read string    $insurance_contract_surname
 * @property-read string    $insurance_contract_activated
 * @property-read string    $insurance_contract_activated_date
 * @property-read string    $insurance_contract_activated_time
 * @property-read int       $insurance_contract_activated_ts
 * @property-read string    $file
 * @property-read string    $avatar
 *
 * @method $this    setId($value)
 * @method $this    setEmail($value)
 * @method $this    setPassword($value)
 * @method $this    setConfirmed($value)
 * @method $this    setStorageTotal($value)
 * @method $this    setStorageUsed($value)
 * @method $this    setFirstName($value)
 * @method $this    setLastName($value)
 * @method $this    setMidName($value)
 * @method $this    setNickname($value)
 * @method $this    setGender($value)
 * @method $this    setSignature($value)
 * @method $this    setBadge($value)
 * @method $this    setRegion($value)
 * @method $this    setLanguage($value)
 * @method $this    setCycleRecording($value)
 * @method $this    setInsuranceCompany_alias($value)
 * @method $this    setInsuranceContract_id($value)
 * @method $this    setInsuranceContract_surname($value)
 * @method $this    setInsuranceContractActivated($value)
 * @method $this    setFile(array $value)
 * @method $this    setAvatar(array $value)
 *
 */
class User extends DbObject {

}