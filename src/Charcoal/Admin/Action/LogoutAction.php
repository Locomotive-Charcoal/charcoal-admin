<?php

namespace Charcoal\Admin\Action;

use Exception;

// From PSR-7
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

// From 'charcoal-admin'
use Charcoal\Admin\AdminAction;
use Charcoal\Admin\User;
use Charcoal\Admin\User\AuthToken;

/**
 * Action: Attempt to log a user out.
 *
 * ## Response
 *
 * - `success` (_boolean_) — TRUE if the user was properly logged out, FALSE in case of any error.
 *
 * ## HTTP Status Codes
 *
 * - `200` — Successful; User has been safely logged out
 * - `500` — Server error; User could not be logged out
 */
class LogoutAction extends AdminAction
{
    /**
     * @param  RequestInterface  $request  A PSR-7 compatible Request instance.
     * @param  ResponseInterface $response A PSR-7 compatible Response instance.
     * @return ResponseInterface
     * @todo   This should be done via an Authenticator object.
     */
    public function run(RequestInterface $request, ResponseInterface $response)
    {
        unset($request);

        try {
            $doneMessage = $this->translator()->translation('You are now logged out.');
            $failMessage = $this->translator()->translation('An error occurred while logging out');
            $errorThrown = strtr($this->translator()->translation('{{ errorMessage }}: {{ errorThrown }}'), [
                '{{ errorMessage }}' => $failMessage
            ]);

            $user = User::getAuthenticated($this->modelFactory());
            if ($user === null) {
                $result = false;

                /** Fail silently — Never confirm or deny the existence of an account. */
                $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
                if ($ip) {
                    $logMessage = sprintf('[Admin] Logout attempt for unauthenticated user from %s', $ip);
                } else {
                    $logMessage = '[Admin] Logout attempt for unauthenticated user';
                }
                $this->logger->warning($logMessage);
            } else {
                $result = $user->logout();
                $this->deleteUserAuthTokens($user);
            }

            $this->setSuccess($result);
            if ($result) {
                $this->addFeedback('success', $doneMessage);

                return $response;
            } else {
                $this->addFeedback('error', $failMessage);

                return $response->withStatus(500);
            }
        } catch (Exception $e) {
            $this->addFeedback('error', strtr($errorThrown, [
                '{{ errorThrown }}' => $e->getMessage()
            ]));
            $this->setSuccess(false);

            return $response->withStatus(500);
        }
    }

    /**
     * @param User $user The user to clear auth tokens for.
     * @return self
     */
    private function deleteUserAuthTokens(User $user)
    {
        $token = $this->modelFactory()->create(AuthToken::class);

        if ($token->source()->tableExists()) {
            $table = $token->source()->table();
            $q = 'DELETE FROM '.$table.' WHERE user_id = :userId';
            $token->source()->dbQuery($q, [ 'userId' => $user->id() ]);
        }

        return $this;
    }

    /**
     * @todo   Provide feedback and redirection?
     * @return array
     */
    public function results()
    {
        return [
            'success' => $this->success()
        ];
    }
}
