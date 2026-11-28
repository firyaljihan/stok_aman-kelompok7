pipeline {
    agent any

    environment {
        IMAGE = 'haurafiryal/stokaman'
        DOCKER_CREDENTIALS_ID = 'stokaman-dockerhub-credentials'
        TRIVY_BIN = 'C:\\Users\\FAM COMPUTINDO\\AppData\\Local\\Microsoft\\WinGet\\Packages\\AquaSecurity.Trivy_Microsoft.Winget.Source_8wekyb3d8bbwe\\trivy.exe'
    }

    options {
        timestamps()
        disableConcurrentBuilds()
    }

    triggers {
        githubPush()
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Build Image') {
            steps {
                bat """
                    docker build -t %IMAGE%:%BUILD_NUMBER% -t %IMAGE%:latest .
                """
            }
        }

        stage('Security Scan (Trivy)') {
            steps {
                bat """
                    "%TRIVY_BIN%" image --exit-code 0 --severity HIGH,CRITICAL %IMAGE%:%BUILD_NUMBER%
                """
            }
        }

        stage('Push to Docker Hub') {
            steps {
                withCredentials([usernamePassword(
                    credentialsId: "${DOCKER_CREDENTIALS_ID}",
                    usernameVariable: 'DOCKER_USER',
                    passwordVariable: 'DOCKER_PASS'
                )]) {
                    bat """
                        echo %DOCKER_PASS% | docker login -u %DOCKER_USER% --password-stdin
                        docker push %IMAGE%:%BUILD_NUMBER%
                        docker push %IMAGE%:latest
                    """
                }
            }
        }

        stage('Deploy') {
            steps {
                bat """
                    docker compose pull app
                    docker compose up -d --remove-orphans
                """
            }
        }

        stage('Verify') {
            steps {
                bat """
                    docker compose ps
                """
            }
        }
    }

    post {
        always {
            bat "docker logout || exit 0"
        }
        success {
            echo "Pipeline StokAman build #${BUILD_NUMBER} berhasil!"
        }
        failure {
            echo "Pipeline StokAman build #${BUILD_NUMBER} gagal. Cek log tiap stage."
        }
    }
}