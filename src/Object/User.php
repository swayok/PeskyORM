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
 * 
 * @method $this    setId($value = null)
 * @method $this    setEmail($value = null)
 * @method $this    setPassword($value = null)
 * @method $this    setConfirmed($value = null)
 * @method $this    setStorageTotal($value = null)
 * @method $this    setStorageUsed($value = null)
 * @method $this    setFirstName($value = null)
 * @method $this    setLastName($value = null)
 * @method $this    setMidName($value = null)
 * @method $this    setNickname($value = null)
 * @method $this    setGender($value = null)
 * @method $this    setSignature($value = null)
 * @method $this    setBadge($value = null)
 * @method $this    setRegion($value = null)
 * @method $this    setLanguage($value = null)
 * @method $this    setCycleRecording($value = null)
 * @method $this    setInsuranceCompany_alias($value = null)
 * @method $this    setInsuranceContract_id($value = null)
 * @method $this    setInsuranceContract_surname($value = null)
 * @method $this    setInsuranceContractActivated($value = null)
 *
 */
class User extends DbObject {

}