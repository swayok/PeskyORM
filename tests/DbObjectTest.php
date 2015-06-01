<?php

namespace Db;

use App\Model\Model;
use PeskyORM\Db;

require_once 'DbObject.php';

class DbObjectTest {

    /** @var Video  */
    static $videoObject;
    /** @var User  */
    static $userObject;

    static public function runTests() {
        self::$videoObject = Model::Video();
        self::$userObject = Model::User();
        self::$videoObject->_getModel()->begin();
        Db::$collectAllQueries = true;
        /*echo '<h1>Find and Read</h1>';
        self::testReading();*/
        /*echo '<h1>Save</h1>';
        self::testSaving();*/
        echo '<h1>Relations</h1>';
        self::testRelations();

        self::$videoObject->model->rollback();
    }

    static public function testRelations() {
        // make test object 1 with video info relation data
        $videoData = array(
            'uuid' => 'test-object',
            'user_id' => self::$userObject->find(array())->id,
            'timezone' => 'UTC+4',
            'rotate' => '90',
            'date' => time(),
            'time' => time(),
            'has_geopoints' => false,
            'visible' => false,
            'VideoInfo' => array(
                'uploading_started' => time(),
                'frames_collector_started' => time(),
                'user_agent' => 'Android|4.2.1|Galaxy Nexus|samsung|api level 1',
                'rotate' => '90',
            ),
        );
        $geopoints = array(
            array(
                'lat' => '59,9831607845',
                'lng' => '59,9831607845',
                'timezone' => 'UTC+4',
                'speed' => '1.2',
                'created' => time()
            ),
            array(
                'lat' => '59,9832607845',
                'lng' => '59,9832607845',
                'timezone' => 'UTC+4',
                'speed' => '1.3',
                'created' => time() + 1
            )
        );
        $success = self::$videoObject->fromData($videoData)->save(false, false, true);
        dpr('$videoData with VideoInfo and user_id: ' . ($success ? 'ok' : 'fail'));
        if (!$success) {
            dpr('validation errors', $success ? '' : self::$videoObject->validationErrors);
            return;
        }
        self::$videoObject->GeoPoint($geopoints);
        self::$videoObject->save(false, false, true);
        self::$videoObject->reload();
//        dpr(self::$videoObject->toPublicArray(), self::$videoObject->relationsToPublicArray(true, false));
        dpr('after save tests:', array(
            'save video' => self::$videoObject->exists() ? 'ok' : 'fail',
            'save video info' => self::$videoObject->VideoInfo->exists() ? 'ok' : 'fail',
            'save geopoints' => count(self::$videoObject->GeoPoint) == 2 ? 'ok' : 'fail',
            'user can be loaded' => self::$videoObject->User->exists() ? 'ok' : 'fail'
        ));

        // how about updating user info with save() on video object?
        $updates = array(
            'uuid' => 'test-object2',
            'User' => array(
                'gender' => 'male'
            )
        );
        $success = self::$videoObject->updateData($updates)->save(false, false, true);
        dpr('Update video field and user field using save(): ' . ($success ? 'ok' : 'fail'));
        if (!$success) {
            dpr('validation errors', $success ? '' : self::$videoObject->validationErrors);
            return;
        }
        self::$videoObject->reload();
//        dpr(self::$videoObject->toPublicArray(), self::$videoObject->relationsToPublicArray(true, false), self::$videoObject->User->validationErrors);
        dpr('after save tests:', array(
            'save video' => self::$videoObject->uuid == $updates['uuid'] ? 'ok' : 'fail',
            'update user' => self::$videoObject->User->gender() == $updates['User']['gender'] ? 'ok' : 'fail'
        ));

        // how about updating user info with begin() + commit() on video object
        $updates = array(
            'uuid' => 'test-object3',
            'User' => array(
                'gender' => 'female'
            )
        );
        $success = self::$videoObject->begin(true)->updateData($updates)->commit(true);
        dpr('Update video field and user field using begin() and commit() with relations: ' . ($success ? 'ok' : 'fail'));
        if (!$success) {
            dpr('validation errors', $success ? '' : self::$videoObject->validationErrors);
            return;
        }
        self::$videoObject->reload();
//        dpr(self::$videoObject->toPublicArray(), self::$videoObject->relationsToPublicArray(true, false), self::$videoObject->User->validationErrors);
        dpr('after save tests:', array(
            'save video' => self::$videoObject->uuid == $updates['uuid'] ? 'ok' : 'fail',
            'update user' => self::$videoObject->User->gender() == $updates['User']['gender'] ? 'ok' : 'fail'
        ));

        // how about updating user info with save() on video object (+ limit saving of relations)?
        $updates = array(
            'uuid' => 'test-object2',
            'User' => array(
                'gender' => 'male'
            )
        );
        $success = self::$videoObject->updateData($updates)->save(false, false, array('User'));
        dpr('Update video field and user field using save() with limited relation: ' . ($success ? 'ok' : 'fail'));
        if (!$success) {
            dpr('validation errors', $success ? '' : self::$videoObject->validationErrors);
            return;
        }
        self::$videoObject->reload();
//        dpr(self::$videoObject->toPublicArray(), self::$videoObject->relationsToPublicArray(true, false), self::$videoObject->User->validationErrors);
        dpr('after save tests:', array(
            'save video' => self::$videoObject->uuid == $updates['uuid'] ? 'ok' : 'fail',
            'update user' => self::$videoObject->User->gender() == $updates['User']['gender'] ? 'ok' : 'fail'
        ));

        // how about updating user info with save() on video object (+ limit saving of relations)?
        $updates = array(
            'uuid' => 'test-object3',
            'User' => array(
                'gender' => 'female'
            )
        );
        $success = self::$videoObject->begin(array('User'))->updateData($updates)->commit(array('User'));
        dpr('Update video field and user field using begin() + commit() with limited relation: ' . ($success ? 'ok' : 'fail'));
        if (!$success) {
            dpr('validation errors', $success ? '' : self::$videoObject->validationErrors);
            return;
        }
        self::$videoObject->reload();
//        dpr(self::$videoObject->toPublicArray(), self::$videoObject->relationsToPublicArray(true, false), self::$videoObject->User->validationErrors);
        dpr('after save tests:', array(
            'save video' => self::$videoObject->uuid == $updates['uuid'] ? 'ok' : 'fail',
            'update user' => self::$videoObject->User->gender() == $updates['User']['gender'] ? 'ok' : 'fail'
        ));
//        dpr(Db::getAllQueries());
        $success = self::$videoObject->delete();
        dpr('Delete video: ' . ($success ? 'ok' : 'fail'));
    }


}