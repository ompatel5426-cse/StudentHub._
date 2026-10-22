/* StudentHub — Student Registration Script */

/* =========================
           SHOW / HIDE PASSWORD
        ========================== */

        function togglePassword(inputId,button) {

            const input = document.getElementById(inputId);

            if (input.type === "password") {

                input.type = "text";

                button.textContent = "🙈";

                button.setAttribute("aria-label","Hide password");

            }

            else {

                input.type = "password";

                button.textContent = "👁";

                button.setAttribute("aria-label","Show password");

            }

        }



        /* =========================
           FORM
        ========================== */

        const form = document.getElementById("registrationForm");


        form.addEventListener("submit", function (event) {

            event.preventDefault();


                /* GET VALUES */

                const name =
                    document.getElementById("name").value.trim();


                const email =
                    document.getElementById("email").value.trim();


                const mobile =
                    document.getElementById("mobile").value.trim();


                const password =
                    document.getElementById("password").value;


                const confirmPassword =
                    document.getElementById("confirmPassword").value;


                const course =
                    document.getElementById("course").value;


                const year =
                    document.getElementById( "year").value;


                const gender =
                    document.querySelector('input[name="gender"]:checked');


                const terms =document.getElementById("terms").checked;



                /* ERROR ELEMENTS */

                const errors = {

                    name:
                        document.getElementById("nameError"),

                    email:
                        document.getElementById("emailError"),

                    mobile:
                        document.getElementById("mobileError"),

                    password:
                        document.getElementById("passwordError"),

                    confirmPassword:
                        document.getElementById("confirmPasswordError"),

                    course:
                        document.getElementById("courseError"),

                    year:
                        document.getElementById("yearError"),

                    gender:
                        document.getElementById("genderError"),

                    terms:
                        document.getElementById("termsError")

                };



                /* CLEAR ERRORS */

                Object.values(errors)
                    .forEach(
                        error =>
                            error.textContent = ""
                    );


                document.getElementById(
                    "successMessage"
                ).style.display = "none";



                let valid = true;



                /* REGEX */

                const nameRegex =
                    /^[A-Za-z ]{2,50}$/;


                const emailRegex =
                    /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/;


                const mobileRegex =
                    /^[6-9][0-9]{9}$/;


                const passwordRegex =
                    /^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[@$!%*?&]).{8,}$/;



                /* NAME */

                if (!nameRegex.test(name)) {

                    errors.name.textContent = "Please enter a valid name.";
                    valid = false;
                }



                /* EMAIL */

                if (!emailRegex.test(email)) {

                    errors.email.textContent = "Please enter a valid email.";
                    valid = false;
                }



                /* MOBILE */

                if (!mobileRegex.test(mobile)) {

                    errors.mobile.textContent = "Enter a valid 10-digit mobile number.";
                    valid = false;
                }



                /* PASSWORD */

                if (!passwordRegex.test(password)) {

                    errors.password.textContent ="Use 8+ characters with uppercase, lowercase, number and special character.";
                    valid = false;
                }



                /* CONFIRM PASSWORD */

                if (password !== confirmPassword) {

                    errors.confirmPassword.textContent ="Passwords do not match.";
                    valid = false;
                }



                /* COURSE */

                if (!course) {

                    errors.course.textContent ="Please select your course.";
                    valid = false;
                }



                /* YEAR */

                if (!year) {

                    errors.year.textContent = "Please select your academic year.";
                    valid = false;
                }



                /* GENDER */

                if (!gender) {

                    errors.gender.textContent = "Please select your gender.";
                    valid = false;
                }



                /* TERMS */

                if (!terms) {

                    errors.terms.textContent = "Please accept the terms and conditions.";
                    valid = false;
                }



                /* SUCCESS */

                if (valid) {

                    form.submit();

                }

            }
        );



        /* =========================
           MOBILE NUMBER
        ========================== */

        document
            .getElementById("mobile")
            .addEventListener(
                "input",
                function () {

                    this.value =
                        this.value
                            .replace(/\D/g, "")
                            .slice(0, 10);

                }
            );

        /* =========================
           CSRF TOKEN AUTO-FETCH
        ========================== */
        fetch('php/csrf.php')
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data && data.csrf_token) {
                    var csrfInput = document.getElementById('csrf_token');
                    if (csrfInput) csrfInput.value = data.csrf_token;
                }
            })
            .catch(function(err) {
                console.warn('CSRF token initialization skipped:', err);
            });
