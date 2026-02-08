#!/bin/bash
set -e  # Exit immediately if a command exits with a non-zero status

# Array of branches to update
#branches=("claude/answer-evaluator-service-011CUsRgomsNSp7HjW4PimNT" "claude/question-generator-service-011CUsR5QbdVxcd49jTmq8yA" "claude/setup-postgres-local-env-011CUqu4NQ9L8JdoHQRpbTnU" "claude/research-agent-refactoring-011CUqtppb61WBqVnEGKn1iK" "claude/reanalyze-legal-war-machine-011CUbU8udjs1XvB5sy1xjRf" "claude/session-011CUaFLw4tKjheWvXwdTvpF")
#branches=("claude/analyze-legal-war-machine-011CUy3LEPqLW4FJxtNbK6Yq" "claude/setup-test-environment-011CUzpL8f3GXLJXcFunRxQC" "claude/setup-postgres-tests-011CUyTRFoMepbzNiV2RPCXS" "claude/test-graphviewer-component-011CUy8L5nVHoJA6u6X46uuh" "claude/test-livewire-legal-playground-011CUy8AMBfgNp2rvqj2vzeX" "claude/test-ingested-laws-manager-011CUy8Uu7o3ZS7yL8VExFfb" "claude/test-decision-discovery-dashboard-011CUy8dzymaA8kCh5JagD6u" "claude/reanalyze-legal-war-machine-011CUbU8udjs1XvB5sy1xjRf" "claude/session-011CUaFLw4tKjheWvXwdTvpF")
#branches=("claude/workflow-superpowers-design-01PNz6S36vM6j8dzj7yR7eJo" "claude/composer-install-setup-01KsydMpTnsPLftbqF4VhCYJ" "claude/writing-plans-subagent-tdd-01XbRoMN7GoKjn7JMgt9yVzM" "claude/analyze-legal-war-machine-011CV2zAPoXFv4HgMsWuDg32")
branches=("claude/recursive-document-writing-agent-01XQGpAV96jAcwgmeB9wGzC3" "claude/setup-composer-install-01GccKuBvxmDanARe58WhJZE" "claude/assess-gen-01VVV5kDSCssQvus4qViUiJL" "claude/pull-remote-changes-018NWjpxCgT6KpwCmMjCSbNf" "claude/composer-install-setup-01KsydMpTnsPLftbqF4VhCYJ" "claude/writing-plans-subagent-tdd-01XbRoMN7GoKjn7JMgt9yVzM" "claude/workflow-superpowers-design-01PNz6S36vM6j8dzj7yR7eJo" "claude/analyze-legal-war-machine-011CV2zAPoXFv4HgMsWuDg32")
# Ensure we start from master and update it
echo "🔹 Checking out master branch..."
git checkout master

echo "🔹 Pulling latest changes from origin/master..."
git pull origin master

# Loop through each branch
for branch in "${branches[@]}"; do
    echo "======================================"
    echo "🔸 Processing branch: $branch"
    echo "======================================"

    # Checkout to the branch
    git checkout "$branch"

    # Pull latest changes from remote
    echo "➡️  Pulling latest changes for $branch..."
    git pull

    # Merge master into the branch
    echo "🔁 Merging master into $branch..."
    git merge master --no-edit

    # Push merged changes
    echo "⬆️  Pushing updated branch $branch..."
    git push

    echo "✅ Finished updating $branch"
    echo ""
done

# Go back to master at the end
git checkout master
echo "🏁 All branches updated successfully."

