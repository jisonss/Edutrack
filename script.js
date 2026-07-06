function showForm(formId) {
  document.querySelectorAll(".form-box").forEach(form => form.classList.remove("active"));
  document.getElementById(formId).classList.add("active");
  if (formId === "register-form") toggleFields();
}

function toggleFields() {
  const role = document.getElementById("roleSelect").value;

  const nameInput = document.getElementById("nameInput");
  const lrnInput = document.getElementById("lrnInput");
  const emailInput = document.getElementById("emailInput");
  const passwordInput = document.getElementById("passwordInput");

  const studentSection = document.getElementById("studentSection");
  const teacherSections = document.getElementById("teacherSections");
  const teacherHint = document.getElementById("teacherHint");

  // hide all
  [nameInput, lrnInput, emailInput, passwordInput].forEach(el => {
    el.style.display = "none";
    el.required = false;
    el.value = "";
  });

  studentSection.style.display = "none";
  studentSection.required = false;
  studentSection.value = "";

  teacherSections.style.display = "none";
  [...teacherSections.options].forEach(o => (o.selected = false));

  teacherHint.style.display = "none";

  if (!role) return;

  // show common
  nameInput.style.display = "block";
  emailInput.style.display = "block";
  passwordInput.style.display = "block";
  nameInput.required = true;
  emailInput.required = true;
  passwordInput.required = true;

  if (role === "user") {
    lrnInput.style.display = "block";
    lrnInput.required = true;

    studentSection.style.display = "block";
    studentSection.required = true;
  }

  if (role === "admin") {
    teacherSections.style.display = "block";
    teacherHint.style.display = "block";
  }
}

document.addEventListener("DOMContentLoaded", toggleFields);
