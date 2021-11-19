# Troubleshooting builds

## Authentication errors with git

### remote: HTTP Basic: Access denied

As of 2021 HTTP basic auth is not supported anymore in GitHub and GitLab. Instead configure token-based authentication in the `auth.json` of your build variant:

```
{
	"github-oauth": {
		"github.com": "your-token-here"
	},
	"gitlab-token":{
		"git1.yourdomain.com": "your-token-here"
	}
}

```

**NOTE:** according to the composer docs, GitLab domains must also be listed in the root `composer.json` as follows:

```
{
	"config": {
		"gitlab-domains":[
			"git1.yourdomain.com"
		]
	}
}

```

## Composer cache errors

Many composer errors can be solved by resetting the composer cache. Each deployer has its own composer cache located in `data/deployer/<project_alias>/.composer`. Just delete the entire composer folder and try again.