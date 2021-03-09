# Troubleshooting builds

## Composer cache errors

Many composer errors can be solved by resetting the composer cache. Each deployer has its own composer cache located in `data/deployer/<project_alias>/.composer`. Just delete the entire composer folder and try again.