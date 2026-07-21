<?php

namespace Core\Nodes\Services;

use Core\Providers\DataTransferObjects\NodeConnectionRequest;

class NodeConnectionSecurityService
{
    public function __construct(
        private readonly NodeHttpClient $http,
        private readonly NodeIpWhitelistService $ipWhitelist,
    ) {
    }

    public function assertAllowed(NodeConnectionRequest $request): void
    {
        $this->http->assertSecureApiUrl($request->apiUrl);
        $this->ipWhitelist->assertAllowed($request);
    }
}
